<?php

namespace QuizApp\Mapper;

class HardCoded implements QuizInterface
{
    public static $MAP = array();

    /**
     * @return \QuizApp\Entity\Quiz[]
     */
    public function findAll()
    {
        return array(
            $this->find(0),
            $this->find(1),
        );
    }

    /**
     * @param int $id
     * @return \QuizApp\Entity\Quiz
     */
    public function find($id)
    {
        if (isset(self::$MAP[$id])) {
            return self::$MAP[$id];
        }
        $result = new \QuizApp\Entity\Quiz(
            'Quiz ' . $id, array(
                new \QuizApp\Entity\Question(
                    'What color was George Washington\'s white horse?',
                    array(
                        'White',
                        'Gray',
                        'Yellow',
                        'All of the above'
                    ),
                    0
                ),
                new \QuizApp\Entity\Question(
                    'Who\'s buried in Grant\'s tomb?',
                    array(
                        'Grant',
                        'George Washington',
                        'George Washinton\'s horse',
                        'All of the above'
                    ),
                    0
                ),
            )
        );
        $result->setId($id);
        self::$MAP[$id] = $result;
        return $result;
    }
}
