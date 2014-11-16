<?php

require 'vendor/autoload.php';

session_start();
$service = new \QuizApp\Service\Quiz(
    new \QuizApp\Mapper\Mongo((new \MongoClient)->practicaloop->quizes)
);

$app = new \Slim\Slim();
$app->config(array('templates.path' => './views'));
$app->get('/', function () use ($service, $app) {
    $app->render('choose-quiz.phtml', array(
        'quizes' => $service->showAllQuizes(),
    ));
});
$app->get('/choose-quiz/:id', function ($id) use ($service, $app) {
    $service->startQuiz($id);
    $app->redirect('/solve-question');
});
$app->get('/solve-question', function () use ($service, $app) {
    $app->render('solve-question.phtml', array(
        'question' => $service->getQuestion(),
    ));
});
$app->post('/check-answer', function () use ($service, $app) {
    $isCorrect = $service->checkSolution($app->request->post('id'));
    if (!$service->isOver()) {
        $app->redirect('/solve-question');
    } else {
        $app->redirect('/end');
    }
});

$app->get('/end', function () use ($service, $app) {
    $app->render('end.phtml', array(
        'result' => $service->getResult(),
    ));
});

$app->run();
