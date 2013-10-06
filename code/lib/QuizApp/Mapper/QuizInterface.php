<?php

namespace QuizApp\Mapper;

interface QuizInterface
{
    /**
     * @return \Entity\Quiz[]
     */
    public function findAll();

    /**
     * @param int $i
     * @return \Entity\Quiz
     */
    public function find($i);
}
