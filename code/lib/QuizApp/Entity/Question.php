<?php

namespace QuizApp\Entity;

class Question
{
    private $id;
    private $question;
    private $solutions;
    private $correctIndex;

    /**
     * @param string $question
     * @param string[] $solutions
     * @param int $correctSolutionIndex
     */
    public function __construct($question, array $solutions, $correctSolutionIndex)
    {
        $this->question = $question;
        $this->solutions = $solutions;
        $this->correctIndex = $correctSolutionIndex;
        if (!isset($this->solutions[$this->correctIndex])) {
            throw new \InvalidArgumentException('Invalid index');
        }
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getQuestion()
    {
        return $this->question;
    }

    public function getSolutions()
    {
        return $this->solutions;
    }

    public function getCorrectSolution()
    {
        return $this->solutions[$this->correctIndex];
    }

    public function isCorrect($solutionId)
    {
        return $this->correctIndex == $solutionId;
    }
}
