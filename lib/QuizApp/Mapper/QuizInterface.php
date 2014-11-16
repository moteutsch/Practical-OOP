<?php

namespace QuizApp\Mapper;

interface QuizInterface
{
    /**
     * @return \QuizApp\Entity\Quiz[]
     */
    public function findAll();

    /**
     * @param int $i
     * @return \QuizApp\Entity\Quiz
     */
    public function find($i);
}
