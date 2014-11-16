<?php

namespace QuizApp\Service\Quiz;

class Result
{
    private $correct;
    private $incorrect;
    private $passScore;

    public function __construct($correct, $incorrect, $passScore)
    {
        $this->correct = $correct;
        $this->incorrect = $incorrect;
        $this->passScore = $passScore;
    }

    public function getCorrect()
    {
        return $this->correct;
    }

    public function getIncorrect()
    {
        return $this->incorrect;
    }

    public function getTotal()
    {
        return $this->correct + $this->incorrect;
    }

    public function getPassScore()
    {
        return $this->passScore;
    }

    public function hasPassed()
    {
        return $this->correct >= $this->passScore;
    }
}
