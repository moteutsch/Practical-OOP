<?php

namespace QuizApp\Service;

interface QuizInterface
{
    /** @return \QuizApp\Entity\Quiz[] */
    public function showAllQuizes();

    public function startQuiz($quizOrId);

    /** @return \QuizApp\Entity\Question */
    public function getQuestion();

    /** @return bool */
    public function checkSolution($id);

    /** @return bool */
    public function isOver();

    /** @return Quiz\Result */
    public function getResult();
}
