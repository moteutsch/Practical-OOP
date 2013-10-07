<?php

namespace QuizApp\Service;

use QuizApp\Service\Quiz\Result;

class Quiz implements QuizInterface
{
    const CURRENT_QUIZ     = 'quizService_currentQuiz';
    const CURRENT_QUESTION = 'quizService_currentQuestion';
    const CORRECT          = 'quizService_correct';
    const INCORRECT        = 'quizService_incorrect';

    private $mapper;

    public function __construct($mapper)
    {
        $this->mapper = $mapper;
    }

    /**
     * @return \Entity\Quiz[]
     */
    public function showAllQuizes()
    {
        // NOTE: We don't want the controller to access the mapper directly, so we create a wrapper function in order to hide it
        return $this->mapper->findAll();
    }

    public function startQuiz($quizOrId)
    {
        if (!$quizOrId instanceof \QuizApp\Entity\Quiz) {
            $quizOrId = $this->mapper->find($quizOrId);
            if ($quizOrId === null) {
                throw new \InvalidArgumentException('Quiz not found');
            }
        }
        $_SESSION[self::CURRENT_QUIZ] = $quizOrId->getId();

        $_SESSION[self::CORRECT] = 0;
        $_SESSION[self::INCORRECT] = 0;
    }

    /**
     * @return Question
     * @throws \LogicException
     */
    public function getQuestion()
    {
        $questions = $this->getCurrentQuiz()->getQuestions();
        $currentQuestion = $this->getCurrentQuestionId();

        // NOTE: Include refactoring step from this to adding a quiz method for count and get($i)
        if ($this->isOver()) {
            throw new \LogicException();
        }
        $result = $questions[$currentQuestion];
        return $result;
    }

    /**
     * @return bool
     */
    public function checkSolution($id)
    {
        $result = $this->getQuestion()->isCorrect($id);
        $_SESSION[self::CURRENT_QUESTION] = $this->getCurrentQuestionId() + 1;
        $this->addResult($result);
        if ($this->isOver()) {
            $_SESSION[self::CURRENT_QUESTION] = null;
            $_SESSION[self::CURRENT_QUIZ] = null;
        }
        return $result;
    }

    /**
     * @return bool
     */
    public function isOver()
    {
        try {
            return $this->getCurrentQuestionId() >= count($this->getCurrentQuiz()->getQuestions());
        } catch (\LogicException $e) {
            return true;
        }
    }

    /**
     * @return Result
     */
    public function getResult()
    {
        return new Result($_SESSION[self::CORRECT], $_SESSION[self::INCORRECT], ($_SESSION[self::CORRECT] + $_SESSION[self::INCORRECT]) / 2);
    }

    private function getCurrentQuiz()
    {
        if (!isset($_SESSION[self::CURRENT_QUIZ])) {
            throw new \LogicException();
        }
        $quiz = $this->mapper->find($_SESSION[self::CURRENT_QUIZ]);
        if ($quiz === null) {
            throw new \LogicException();
        }
        return $quiz;
    }

    private function getCurrentQuestionId()
    {
        return isset($_SESSION[self::CURRENT_QUESTION]) ? $_SESSION[self::CURRENT_QUESTION] : 0;
    }

    private function addResult($isCorrect)
    {
        $type = ($isCorrect ? self::CORRECT : self::INCORRECT);
        if (!isset($_SESSION[$type])) {
            $_SESSION[$type] = 0;
        }
        $_SESSION[$type] += 1;
    }
}
