<?php
/*
Plugin Name: MasterStudy LMS Quiz Retry Addon
Description: Adiciona a funcionalidade de re-tentativa em quizzes do MasterStudy LMS, utilizando o Course Builder para definir a opção e aproveitando o objeto global quiz_data para obter o quiz ID.
Version: Beta 1.7
Author: 
License: GPLv3
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Impede acesso direto
}

/* 1. Registra o campo customizado "Allow Quiz Retry" no Course Builder */
function ms_lms_add_retry_custom_field( $custom_fields ) {
    $custom_fields[] = array(
        'type'    => 'checkbox',
        'name'    => 'allow-quiz-retry',
        'label'   => __( 'Allow Quiz Retry', 'masterstudy-lms-learning-management-system' ),
        'default' => false,
    );
    return $custom_fields;
}
add_filter( 'masterstudy_lms_quiz_custom_fields', 'ms_lms_add_retry_custom_field' );

/* 2. Filtro para processar blocos do tipo "core/shortcode" e extrair o quiz ID */
add_filter('render_block', 'ms_lms_render_shortcode_block', 10, 2);
function ms_lms_render_shortcode_block($block_content, $block) {
    if ( isset($block['blockName']) && $block['blockName'] === 'core/shortcode' ) {
        $processed = do_shortcode( $block['innerHTML'] );
        if ( preg_match('/\[stm_lms_quiz_online\s+id\s*=\s*["\']?(\d+)["\']?\]/i', $processed, $matches) ) {
            global $ms_lms_quiz_id;
            $ms_lms_quiz_id = intval($matches[1]);
            error_log("Quiz ID extraído do bloco shortcode: " . $ms_lms_quiz_id);
        }
        return $processed;
    }
    return $block_content;
}

/* 3. Função para extrair o quiz ID pela URL (último segmento) */
function ms_lms_get_quiz_id_from_url() {
    if ( isset( $_SERVER['REQUEST_URI'] ) ) {
        $uri = trim( $_SERVER['REQUEST_URI'], '/' );
        $parts = explode( '/', $uri );
        $last = end( $parts );
        if ( is_numeric( $last ) ) {
            return intval( $last );
        }
    }
    return 0;
}

/* 4. Define a variável global para o quiz ID.
   Se não for definido pelo bloco de shortcode, usa o valor da URL */
global $ms_lms_quiz_id;
if ( empty( $ms_lms_quiz_id ) ) {
    // Utilize um hook mais tarde para ter certeza de que o ambiente está carregado
    add_action('wp', function() {
        global $ms_lms_quiz_id;
        $ms_lms_quiz_id = ms_lms_get_quiz_id_from_url();
        error_log("ms_lms_quiz_id definido via URL: " . $ms_lms_quiz_id);
    });
}

/* 5. Enfileira o script e passa as variáveis para o JavaScript */
function ms_lms_enqueue_retry_script() {
    global $ms_lms_quiz_id;
    $quiz_id = ($ms_lms_quiz_id) ? $ms_lms_quiz_id : get_queried_object_id();
    $meta_value = get_post_meta( $quiz_id, 'allow-quiz-retry', false );
    $allow_flag = (!empty($meta_value) && in_array('1', $meta_value));
    
    wp_enqueue_script(
        'ms-lms-quiz-retry',
        plugin_dir_url( __FILE__ ) . 'js/quiz-retry.js',
        array( 'jquery' ),
        '1.0',
        true
    );
    wp_localize_script( 'ms-lms-quiz-retry', 'msLmsQuizRetry', array(
        'nonce'   => wp_create_nonce( 'ms_lms_quiz_retry_nonce' ),
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'allow'   => $allow_flag,
        'quiz_id' => $quiz_id,
    ));
}
add_action( 'wp_enqueue_scripts', 'ms_lms_enqueue_retry_script' );

/* 6. Atualiza os valores do objeto JavaScript no wp_footer usando quiz_data como fallback */
add_action('wp_footer', 'ms_lms_update_quiz_retry_from_quiz_data');
function ms_lms_update_quiz_retry_from_quiz_data() {
    ?>
    <script type="text/javascript">
        (function(){
            if (typeof quiz_data !== 'undefined' && quiz_data.quiz_id) {
                msLmsQuizRetry.quiz_id = quiz_data.quiz_id;
                console.log("Atualizado msLmsQuizRetry.quiz_id via quiz_data:", msLmsQuizRetry.quiz_id);
            } else {
                console.log("quiz_data não definido; usando msLmsQuizRetry.quiz_id:", msLmsQuizRetry.quiz_id);
            }
        })();
    </script>
    <?php
}
?>
