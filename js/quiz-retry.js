jQuery(document).ready(function($) {
    // Verifica se estamos na página de resultado pelo contêiner
    if ($('.masterstudy-course-player-quiz__result-container').length > 0) {
        // Verifica se o meta allow está definido como true
        if (msLmsQuizRetry.allow) {
            // Se o botão de retake ainda não existir, injetamos ele
            if ($('#quiz-result-retake').length === 0) {
                // Cria o HTML do botão
                var buttonHtml = '<div class="masterstudy-course-player-quiz__result-retake">' +
                                 '<button id="quiz-result-retake" class="btn btn-primary btn-sm">Retake</button>' +
                                 '</div>';
                // Insere o botão no final do contêiner de resultado
                $('.masterstudy-course-player-quiz__result-container').append(buttonHtml);
            }
        }
    }
    
    // Opcional: Vincula um evento de clique ao botão
    $(document).on('click', '#quiz-result-retake', function(e) {
        e.preventDefault();
        // Aqui você pode chamar a função AJAX para iniciar a nova tentativa ou redirecionar o usuário
        // Exemplo simples: alerta de teste
        alert('Retake quiz acionado! Implementar nova tentativa...');
        // Você pode, por exemplo, chamar:
        // $.ajax({ ... });
    });
});
