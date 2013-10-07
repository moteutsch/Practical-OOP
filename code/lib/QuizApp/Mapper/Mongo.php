<?php

namespace QuizApp\Mapper;

class Mongo implements QuizInterface
{
    public static $MAP = array();

    /**
     * @var \MongoCollection
     */
    private $collection;

    public function __construct(\MongoCollection $collection)
    {
        $this->collection = $collection;
    }

    /**
     * @return \QuizApp\Entity\Quiz[]
     */
    public function findAll()
    {
        $entities = array();
        $results = $this->collection->find();
        foreach ($results as $result) {
            $entities[] = $e = $this->rowToEntity($result);
            $this->cacheEntity($e);
        }
        return $entities;
    }

    /**
     * @param int $id
     * @return \QuizApp\Entity\Quiz
     */
    public function find($id)
    {
        $id = (string) $id;
        if (isset(self::$MAP[$id])) {
            return self::$MAP[$id];
        }
        $row = $this->collection->findOne(array('_id' => new \MongoId($id)));
        if ($row === null) {
            return null;
        }
        $entity = $this->rowToEntity($row);
        $this->cacheEntity($entity);
        return $entity;
    }

    private function cacheEntity($entity)
    {
        self::$MAP[(string) $entity->getId()] = $entity;
    }

    private function rowToEntity($row)
    {
        $result = new \QuizApp\Entity\Quiz(
            $row['title'], array_map(function ($question) {
                return new \QuizApp\Entity\Question(
                    $question['question'],
                    $question['solutions'],
                    $question['correctIndex']
                );
            }, $row['questions'])
        );
        $result->setId($row['_id']);
        return $result;
    }
}
