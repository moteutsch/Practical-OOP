<?php

namespace QuizApp\Service;

interface QuizInterface
{
    /**
     * @return \Entity\Quiz[]
     */
    public function showAllQuizes();

    public function startQuiz($quizOrId);

    /**
     * @return Question
     */
    public function getQuestion();

    /**
     * @return bool
     */
    public function checkSolution($id);

    /**
     * @return bool
     */
    public function isOver();

    /**
     * @return Result
     */
    public function getResult();
}
