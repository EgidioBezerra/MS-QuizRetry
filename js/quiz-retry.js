jQuery(document).ready(function($) {
    console.log("msLmsQuizRetry no início:", msLmsQuizRetry);
    
    // Se a opção de retake estiver ativada, injeta o botão "Retake"
    if (msLmsQuizRetry.allow) {
        var interval = setInterval(function() {
            var $container = $('.masterstudy-course-player-quiz__result-container');
            console.log("Procurando contêiner de resultado, encontrados:", $container.length);
            if ($container.length > 0) {
                if ($('#quiz-result-retake').length === 0) {
                    var buttonHtml = '<div class="masterstudy-course-player-quiz__result-retake">' +
                                     '<button id="quiz-result-retake" class="btn btn-primary btn-sm">Retake</button>' +
                                     '</div>';
                    $container.append(buttonHtml);
                    console.log("Botão de retake injetado.");
                }
                clearInterval(interval);
            }
        }, 500);
    } else {
        console.log("Opção de retake não ativada (msLmsQuizRetry.allow é false).");
    }
    
    // Evento de clique para o botão "Retake"
    $(document).on('click', '#quiz-result-retake', function(e) {
        e.preventDefault();
        alert('Retake quiz acionado! Implemente a lógica de retry aqui.');
    });
});
