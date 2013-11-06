# Practical OOP: Creating a Quiz App
## Introduction

At a certain of my development as a PHP programmer, I was building MVC applications by-the-book, without understanding the ins-and-outs. I did what I was told: fat model, thin controller. Don't put logic in your views. What I didn't understand was how to creative a cohesive application structure that allowed me to express my business ideas as maintainable, navigable code. Nor did I understand how to really separate my concerns into tight layers without leaking low-level logic into higher layers. I'd heard about SOLID principles, but applying them to a web app was a mystery.

In this article, we'll build a quiz application using these concepts. We'll separate the application into layers, allowing us to substitute components: for example, it'll be a breeze to switch from MongoDB to MySQL, or from a web interface to a command-line interface.

## Why MVC Isn't Enough

MVC, which stands for Model-View-Controller, is a powerful design pattern for web applications. Unfortunately, with its rise to buzzword status, it has been taken out of context and used as a miracle cure. It's become standard practice to use MVC frameworks, and many developers have succeeded in using them to separate display logic and domain logic. The trouble is that developers stop there, building quasi-object-oriented systems at best and procedural code wrapped in classes--often controllers--at worst.

In building our quiz application, we'll be using the Domain Model pattern described in Martin Fowler's Patterns of Enterprise Application Architecture. Domain Model is just a fancy way of saying that we'll be using an object-oriented approach to designing the system: a web of objects with different responsibilities that, as a whole, will comprise our application.

The Domain Model approach uses "entity" objects to represent information in the database; but instead of having our object-oriented code mimic the database, we'll have the database mimic our object-oriented design. Why? Because it allows us to build good object-oriented code. This mapping, called Object-Relational Mapping, is a large subject, and outside of the scope of this article. Luckily there are several mature libraries available in PHP that solve this problem[1]. We'll be side-stepping the entirely issue by manually writing the specific mapping code we need for this article.

Even when using the Domain Model pattern there is still the problem of preforming operations that require multiple classes to work together. We'll be solving this with the Service Layer pattern.

## The Service Layer Pattern

Correct object-oriented design dictates that you should write decoupled code. Each class should have a single responsibility. Well, how then do we combine these independent classes to perform our business logic?

The Service Layer pattern addresses this problem. We group all our systems operations (e.g., signing up, sending invitation emails to friends, etc.) into service classes, one service per operation, or group of closely-related operations. We decouple these service classes from the classes they use to do their job. This allows us to reuse the services between different use-cases, say the web interface and the CLI interface, the front- and back-end interfaces, and so on.

[Where to put this?: [For the web interface, we'll need three pages: a page for choosing a quiz, a page for answering questions, and a "results" page with the users score. The controllers for these pages will just pass the request data to the service and then send the results to the view.]]

## Getting Started

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

## Coding the Service Class

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

The class implements the interface by returning a couple of hard-coded `Quiz` objects. It uses the `$map` static property as an Identity Map to ensure the class returns the same objects each time it's called.

## Controllers and Views with Slim

Now that we've finished setting up the "M" of our MVC application, it's time to write our controllers as views. We're using the Slim framework, but it's easy to replace Slim with any other MVC framework since most of our code is decoupled from the framework.

Create a `index.php` file with the following contents:

    <?php

    require 'vendor/autoload.php';
    session_start();

    $service = new \QuizApp\Service\Quiz(
        new \QuizApp\Mapper\HardCode()
    );

    $app = new \Slim\Slim();
    $app->config(array('templates.path' => './views'));

    // Controller actions here

    $app->run();

This is the base of our Slim application. We `require` our Composer autoload file that we generated earlier. This autoloads the Slim library files as well as our Model code. Next create our service and start the PHP session, since we use the `$_SESSION` superglobal in our service. Finally we setup our Slim application.

Let's create the homepage first. The homepage will list the quizes the user can take. The controller code for this is straightforward. Add the following by the comment in our `index.php` file.

    $app->get('/', function () use ($service, $app) {
        $app->render('choose-quiz.phtml', array(
            'quizes' => $service->showAllQuizes(),
        ));
    });

We define a new home page route, "/", and render the "choose-quiz.phtml" view file, passing it the list of our quizes we retrieved from the service. Let's write the view file.

    <h3>Choose a Quiz</h3>

    <ul>
        <?php foreach ($quizes as $quiz): ?>
        <li><a href="choose-quiz/<?php echo $quiz->getId(); ?>"><?php echo $quiz->getTitle(); ?></a></li>
        <?php endforeach; ?>
    </ul>

At this point the you should be able to go to the home page of the application and see the two quizes we hard-coded earlier, Quiz 1 and Quiz 2.

The quiz links point to "choose-quiz/:id", where ":id" is the ID of the quiz. This URL should choose the quiz the user clicked and redirect him to the first question. Add the following route to `index.php`:

    $app->get('/choose-quiz/:id', function ($id) use ($service, $app) {
        $service->startQuiz($id);
        $app->redirect('/solve-question');
    });



----

    $app->get('/solve-question', function () use ($service, $app) {
        $app->render('solve-question.phtml', array(
            'question' => $service->getQuestion(),
        ));
    });
    $app->post('/check-answer', function () use ($service, $app) {
        $isCorrect = $service->checkSolution($app->request->post('id'));
        if (!$service->isOver()) {
            $app->redirect('/solve-question');
        } else {
            $app->redirect('/end');
        }
    });

    $app->get('/end', function () use ($service, $app) {
        $app->render('end.phtml', array(
            'result' => $service->getResult(),
        ));
    });

## Writing a Real Mapper with MongoDB

## Conclusion

---

1: The one I'd recommend is called Doctrine 2.

---

TODO:

+ Mention setting up virtual host for site.
+ Changing between "you" and "we" when narrating the code
+ The "create the following" vs. "I've written it and am explaining it to you" conundrum.
