<?php
/*
Plugin Name: MasterStudy LMS Quiz Retry Addon
Description: Adiciona a funcionalidade de re-tentativa em quizzes do MasterStudy LMS, utilizando o Course Builder para definir a opção e aproveitando o objeto global quiz_data para obter o quiz ID.
Version: Beta 1.6
Author: 
License: GPLv3
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Impede acesso direto
}

/* 1. Registra o campo customizado "Allow Quiz Retry" no Course Builder.
   Este campo adiciona um checkbox no editor do quiz. */
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

/* 2. Função para extrair o quiz ID da URL.
   Essa função pega o último segmento da URL, se for numérico. */
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

/* 3. Define uma variável global para armazenar o quiz ID.
   Para garantir que o ambiente esteja pronto (com $post e URL definidos), usamos o hook 'wp'. */
global $ms_lms_quiz_id;
$ms_lms_quiz_id = 0;
add_action('wp', 'ms_lms_set_global_quiz_id');
function ms_lms_set_global_quiz_id() {
    global $ms_lms_quiz_id;
    $ms_lms_quiz_id = ms_lms_get_quiz_id_from_url();
    error_log("ms_lms_quiz_id definido via URL: " . $ms_lms_quiz_id);
}

/* 4. Enfileira o script e passa as variáveis para o JavaScript.
   Utilizamos o quiz ID definido na etapa anterior e lemos o meta "allow-quiz-retry". */
function ms_lms_enqueue_retry_script() {
    global $ms_lms_quiz_id;
    $quiz_id = ($ms_lms_quiz_id) ? $ms_lms_quiz_id : get_queried_object_id();
    
    // Recupera o meta "allow-quiz-retry" como array
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

/* 5. Atualiza os valores do objeto JavaScript no wp_footer.
   Aqui, usamos o objeto global quiz_data, definido pelo LMS, se estiver disponível.
   Dessa forma, se o quiz estiver no ambiente do Course Builder, o ID correto é utilizado. */
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