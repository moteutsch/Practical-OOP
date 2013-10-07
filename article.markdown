# Practical OOP: Creating a Quiz App
## Introduction

At a certain of my development as a PHP programmer, I was building MVC applications by-the-book, without understanding the ins-and-outs. I did what I was told: fat model, thin controller. Don't put logic in your views. What I didn't understand was how to creative a cohesive application structure that allowed me to express my business ideas as maintainable, navigable code. Nor did I understand how to really separate my concerns into tight layers without leaking low-level logic into higher layers. I'd heard about SOLID principles, but applying them to a web app was a mystery.

In this article, we'll build a quiz application using these concepts. We'll separate the application into layers, allowing us to substitute components: for example, it'll be a breeze to switch from MongoDB to MySQL, or from a web interface to a command-line interface.

## Setup

We'll be using Slim for the MVC framework. We'll install Slim with Composer, the dependency management tool. Create a directory for the project and run the following command inside:

    curl -sS https://getcomposer.org/installer | php

Create a `composer.json` file with the following contents:

    {
      "require": {
        "slim/slim": "2.*"
      },
      "autoload": {
        "psr-0": {"QuizApp\\": "./lib/"}
      }
    }

This adds Slim as a project dependency, and sets up autoloading for our classes.

Now have Composer install Slim by running:

    php composer.phar update

## Planning the Code

MVC is a great idiom for separating logic from presentation, but it leaves the "model" part (which, as we'll see, is most of the code) unclear[better word].

We'll be using a service-oriented design. Each distinct business operation gets a service class which is contains the business logic for that operation, regardless of whether the request is sent from a regular user action, a API request, or an administrator's action from the command-line.

The service doesn't do all the work itself: it delegates to other classes--most commonly the "mapper" classes which are responsible for accessing the database.

For the web interface, we'll need three pages: a page for choosing a quiz, a page for answering questions, and a "results" page with the users score. The controllers for these pages will just pass the request data to the service and then send the results to the view.

## The Service

We'll need a service for handling the quiz flow: choosing a quiz, checking the users answers, and so on. This will contain bulk of the business logic of the application. The rest will solve technical problems, like accessing the database.

Let's define an interface for the service. Create a file `lib/QuizApp/Service/QuizInterface.php` with the following contents:

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

Most of the operations should speak for themselves, but `getQuestion()` and `getResult()` might not be so clear. `getQuestion()` returns the next question for the user to answer. `getResult` returns an object with information about the number of correct and incorrect answers, and whether the user passed the quiz. 

Before we implement this service, we should define the mapper interface, as the service will need to use it. [Create a more specific interface: QuizFinder]. The service needs two operations: `find()` which returns a single quiz by ID, and `findAll()`.

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

These operations return objects of the class `\QuizApp\Entity\Quiz`. The class stores the information of a single quiz. The class also makes available `\QuizApp\Entity\Question` objects, containing information about quiz questions. Let's implement these before returning to the service.

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

Notice that `\QuizApp\Entity\Question` has a method `isCorrect()` for checking if a certain answer to the question is correct.

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

Okay, now that we've defined the interface for the mapper and created the entity classes, we have all the building blocks we need for creating the service.

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

        public function __construct(\QuizApp\Mapper\QuizInterface $mapper)
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

That's a long one. Let's go over it method by method.

## Controllers and Views with Slim

## Writing a Real Mapper with MongoDB

## Conclusion
