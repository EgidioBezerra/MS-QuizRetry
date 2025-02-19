<?php
/*
Plugin Name: MasterStudy LMS Quiz Retry Addon
Description: Adiciona a funcionalidade de re-tentativa em quizzes do MasterStudy LMS, com opção via Course Builder.
Version: Beta 1.4
Author: Egidio
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

/* 2. Função para extrair o ID do quiz pela URL (último segmento) */
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

/* 3. Define uma variável global para armazenar o ID do quiz */
global $ms_lms_quiz_id;
$ms_lms_quiz_id = ms_lms_get_quiz_id_from_url();

/* 4. Enfileira o script e passa as variáveis para o JavaScript */
function ms_lms_enqueue_retry_script() {
    global $ms_lms_quiz_id;
    $quiz_id = $ms_lms_quiz_id;
    
    // Recupera o meta "allow-quiz-retry" como array
    $meta_value = get_post_meta( $quiz_id, 'allow-quiz-retry', false );
    $allow_flag = ( ! empty( $meta_value ) && in_array( '1', $meta_value ) );
    
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
    ) );
}
add_action( 'wp_enqueue_scripts', 'ms_lms_enqueue_retry_script' );

/* 5. Atualiza os valores do objeto JavaScript no wp_footer */
function ms_lms_update_quiz_retry_localized() {
    global $ms_lms_quiz_id;
    $meta_value = get_post_meta( $ms_lms_quiz_id, 'allow-quiz-retry', false );
    $allow_flag = ( ! empty( $meta_value ) && in_array( '1', $meta_value ) );
    ?>
    <script type="text/javascript">
        if ( typeof msLmsQuizRetry !== 'undefined' ) {
            msLmsQuizRetry.quiz_id = <?php echo json_encode( $ms_lms_quiz_id ); ?>;
            msLmsQuizRetry.allow = <?php echo json_encode( $allow_flag ); ?>;
            console.log("Atualizado msLmsQuizRetry:", msLmsQuizRetry);
        }
    </script>
    <?php
}
add_action( 'wp_footer', 'ms_lms_update_quiz_retry_localized' );
?>
