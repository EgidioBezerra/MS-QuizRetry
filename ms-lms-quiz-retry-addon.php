<?php
/*
Plugin Name: MasterStudy LMS Quiz Retry Addon
Description: Adiciona a funcionalidade de re-tentativa em quizzes do MasterStudy LMS, utilizando o Course Builder para definir a opção e aproveitando o objeto global quiz_data para obter o quiz ID.
Version: Beta 1.9
Author: 
License: GPLv3
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Impede acesso direto
}

/* 1. Registra o campo customizado "Allow Quiz Retry" no Course Builder.
   Este campo adiciona um checkbox no editor de quiz, salvando o valor na meta "allow-quiz-retry". */
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

/* 2. Utiliza o filtro "pre_do_shortcode_tag" para capturar o ID do quiz
   antes que o shortcode seja processado. */
add_filter('pre_do_shortcode_tag', 'ms_lms_pre_do_shortcode_tag', 10, 4);
function ms_lms_pre_do_shortcode_tag($return, $tag, $atts, $m) {
    if ($tag === 'stm_lms_quiz_online') {
        global $ms_lms_quiz_id;
        $atts = shortcode_atts( array( 'id' => 0 ), $atts, 'stm_lms_quiz_online' );
        $ms_lms_quiz_id = intval($atts['id']);
        error_log("Quiz ID capturado via pre_do_shortcode_tag: " . $ms_lms_quiz_id);
    }
    return $return; // Não altera o resultado do shortcode
}

/* 3. Fallback: Se o shortcode não for processado corretamente (por exemplo, aparece como literal),
   utiliza o filtro "the_content" para tentar extrair o ID do quiz. */
function ms_lms_extract_quiz_id_from_content_fallback($content) {
    global $ms_lms_quiz_id;
    if ( empty($ms_lms_quiz_id) && strpos($content, '[stm_lms_quiz_online') !== false ) {
        if ( preg_match('/\[stm_lms_quiz_online\s+id\s*=\s*["\']?(\d+)["\']?\]/i', $content, $matches) ) {
            $ms_lms_quiz_id = intval($matches[1]);
            error_log("Quiz ID capturado via fallback the_content: " . $ms_lms_quiz_id);
        }
    }
    return $content;
}
add_filter('the_content', 'ms_lms_extract_quiz_id_from_content_fallback', 20);

/* 4. Fallback: Extrai o quiz ID pela URL (último segmento).
   Esse método funciona bem em cursos, quando a URL termina com o ID. */
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

/* 5. Define a variável global para o quiz ID.
   Prioriza o valor capturado pelo shortcode/fallback; se não existir, usa o valor da URL. */
global $ms_lms_quiz_id;
if ( empty( $ms_lms_quiz_id ) ) {
    add_action('wp', function() {
        global $ms_lms_quiz_id;
        $ms_lms_quiz_id = ms_lms_get_quiz_id_from_url();
        error_log("Quiz ID definido via URL fallback: " . $ms_lms_quiz_id);
    });
}

/* 6. Enfileira o script e passa as variáveis para o JavaScript.
   Usa o quiz ID definido (por shortcode, fallback ou URL) e lê o meta "allow-quiz-retry". */
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

/* 7. Atualiza os valores do objeto JavaScript no wp_footer usando o objeto global quiz_data como fallback.
   Se quiz_data.quiz_id estiver definido, atualiza msLmsQuizRetry.quiz_id com esse valor. */
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