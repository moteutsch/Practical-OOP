<?php

namespace QuizApp\Entity;

class Quiz
{
    private $id;
    private $title;
    private $questions;

    /**
     * @param string $title
     * @param Question[] $questions
     */
    public function __construct($title, array $questions)
    {
        $this->title     = $title;
        $this->questions = $questions;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }


    public function getTitle()
    {
        return $this->title;
    }

    public function getQuestions()
    {
        return $this->questions;
    }
}
