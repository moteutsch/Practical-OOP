# Practical OOP: Creating a Quiz App
## Introduction

At a certain of my development as a PHP programmer, I was building MVC applications by-the-book, without understanding the ins-and-outs. I did what I was told: fat model, thin controller. Don't put logic in your views. What I didn't understand was how to creative a cohesive application structure that allowed me to express my business ideas as maintainable, navigable code. Nor did I understand how to really separate my concerns into tight layers without leaking low-level logic into higher layers. I'd heard about SOLID principles, but applying them to a web app was a mystery.

In this article, we'll build a quiz application using these concepts. [This is kinda off-point.] We'll separate the application into layers, allowing us to substitute components: for example, it'll be a breeze to switch from MongoDB to MySQL, or from a web interface to a command-line interface.

## Why MVC Isn't Enough

MVC, which stands for Model-View-Controller, is a powerful design pattern for web applications. Unfortunately, with its rise to buzzword status, it has been taken out of context and used as a miracle cure. It's become standard practice to use MVC frameworks, and many developers have succeeded in using them to separate display logic and domain logic. The trouble is that developers stop there, building quasi-object-oriented systems at best and procedural code wrapped in classes--often controllers--at worst.

In building our quiz application, we'll be using the [Domain Model](http://martinfowler.com/eaaCatalog/domainModel.html) pattern described in Martin Fowler's [Patterns of Enterprise Application Architecture](http://martinfowler.com/books/eaa.html). Domain Model is just a fancy way of saying that we'll be using an object-oriented approach to designing the system: a web of objects with different responsibilities that, as a whole, will comprise our application.

The Domain Model approach uses "entity" objects to represent information in the database; but instead of having our object-oriented code mimic the database, we'll have the database mimic our object-oriented design. Why? Because it allows us to build good object-oriented code. This mapping, called Object-Relational Mapping, is a large subject, and outside of the scope of this article. Luckily there are several mature libraries available in PHP that solve this problem[1]. We'll be side-stepping the entirely issue by manually writing the specific mapping code we need for this article.

Even when using the Domain Model pattern there is still the problem of preforming operations that require multiple classes to work together. We'll be solving this with the Service Layer pattern.

## The Service Layer Pattern

Correct object-oriented design dictates that you should write decoupled code. Each class should have a single responsibility. Well, how then do we combine these independent classes to perform our business logic?

The Service Layer pattern addresses this problem. We group all our systems operations (e.g., signing up, sending invitation emails to friends, etc.) into service classes, one service per operation or group of closely-related operations. We decouple these service classes from the classes they use to do their job. This allows us to reuse the services between different use-cases, say the web interface and the CLI interface, the front- and back-end interfaces, and so on.

## Getting Started

We'll be using Slim as our MVC framework. We'll install Slim with Composer, the dependency management tool. Create a directory for the project and run the following command inside:

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

Before we implement this service, we should define the mapper interface, as the service will need to use it. The service needs two operations: `find()` which returns a single quiz by ID, and `findAll()`.

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

These operations return objects of the class `\QuizApp\Entity\Quiz`, which represents a single quiz. The class contains `\QuizApp\Entity\Question` objects, which represent quiz questions. Let's implement these before returning to the service.

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

Notice that, in addition to its getters and setters, `\QuizApp\Entity\Question` has a method `isCorrect()` for checking if a certain answer to the question is correct.

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

Okay, now that we've defined the interface for the mapper and created the entity classes, we have all the building blocks we need for implementing a concrete service class.

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

            if ($this->isOver()) {
                throw new \LogicException();
            }
            $result = $questions[$currentQuestion];
            return $result;
        }

        /**
         * @return bool
         */
        public function checkSolution($solutionId)
        {
            $result = $this->getQuestion()->isCorrect($solutionId);
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

The `showAllQuizes()` method wraps the `QuizMapper::findAll()` method. We could make `$mapper` public, but we'd break encapsulation by leaking low-level operations to the higher level classes.

The `startQuiz()` method begins the quiz passed as an argument by storing the quiz in the session for future reference. It accepts either a quiz entity object or a quiz ID, in which case it tries to find the quiz using the `$mapper`. The method uses the `$_SESSION` superglobal directly, which isn't best practice--the service would break if used in a command-line context, for instance--, but there's no need to over-complicate the service yet. Later we would use a session interface that is implementation ambivalent [wrong word] for the user to pass the correct instance of for his purposes. [Re-work sentence]

The `getQuestion()` method tries getting the next question of the current quiz from the database, delegating to other helpers methods, and throws an exception if the quiz is over or the user isn't in the middle of a quiz.

The `checkSolution()` method returns whether the user's solution is correct, and updates the session to reflect the state of the quiz after the question is answered.

The `isOver()` method returns true if the current quiz is over or if no quiz is underway.

The `getResult()` method returns a `QuizApp\Service\Quiz\Result` object that tells the user whether he passed the quiz and how many questions he answer correctly. [Should I write out this class, or mention that the reader can see it in the code sample, or what? And how about the other POJO, wrapper classes? SHould I write them out in the code (like I've done), or what?]

[If defining the Result class, it should be here.]

## Writing a Placeholder Mapper

[Mention that we're writing a placeholder mapper and that it's only supposed to be a placeholder so we can easily test the code, etc.]

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
        new \QuizApp\Mapper\HardCoded()
    );

    $app = new \Slim\Slim();
    $app->config(array('templates.path' => './views'));

    // Controller actions here

    $app->run();

This is the base of our Slim application. We `require` the Composer autoload file that we generated earlier. This autoloads the Slim library files as well as our Model code. Next create our service and start the PHP session, since we use the `$_SESSION` superglobal in our service. Finally we setup our Slim application.

Let's create the homepage first. The homepage will list the quizes the user can take. The controller code for this is straightforward. Add the following by the comment in our `index.php` file.

    $app->get('/', function () use ($service, $app) {
        $app->render('choose-quiz.phtml', array(
            'quizes' => $service->showAllQuizes(),
        ));
    });

We define the home page route with the `$app->get()` method. We pass the route as the first parameter and pass the code to run as the second parameter, in the form of an anonymous function. In the function we render the "choose-quiz.phtml" view file, passing it the list of our quizes we retrieved from the service. Let's code the view.

    <h3>Choose a Quiz</h3>

    <ul>
        <?php foreach ($quizes as $quiz): ?>
        <li><a href="choose-quiz/<?php echo $quiz->getId(); ?>"><?php echo $quiz->getTitle(); ?></a></li>
        <?php endforeach; ?>
    </ul>


At this point, if you navigate to the home page of the app with your browser, you'll see the two quizes we hard-coded earlier, "Quiz 1" and "Quiz 2."

The quiz links on the home page point to "choose-quiz/:id", where ":id" is the ID of the quiz. This route should start the quiz that the user chose and redirect him to its first question. Add the following route to `index.php`:

    $app->get('/choose-quiz/:id', function ($id) use ($service, $app) {
        $service->startQuiz($id);
        $app->redirect('/solve-question');
    });

Now let's define the "/solve-question" route. This route will show the user the current question of the quiz he is solving.

    $app->get('/solve-question', function () use ($service, $app) {
        $app->render('solve-question.phtml', array(
            'question' => $service->getQuestion(),
        ));
    });

The route renders the view "solve-question.phtml" with the question returned from the service. Let's define the view.

    <h3><?php echo $question->getQuestion(); ?></h3>

    <form action="check-answer" method="post">
        <ul>
            <?php $solutions = $question->getSolutions(); ?>
            <?php foreach ($solutions as $id => $solution): ?>
                <li><input type="radio" name="id" value="<?php echo $id; ?>"> <?php echo $solution; ?></li>
            <?php endforeach; ?>
        </ul>

        <input type="submit" value="Submit">
    </form>

We show the user a form with a radio button per answer. The form sends the result to the "check-answer" route.

    $app->post('/check-answer', function () use ($service, $app) {
        $isCorrect = $service->checkSolution($app->request->post('id'));
        if (!$service->isOver()) {
            $app->redirect('/solve-question');
        } else {
            $app->redirect('/end');
        }
    });

This time we're defining a route for "POST" requests, so we use the `$app->post()` method. To get the solution ID sent by the user we call `$app->request->post('id')`. The service returns whether this answer was correct. If there are more questions for the user to answer, we redirect him back to the "solve-question" route. If he's finished the quiz, we send him to the "end" route. This should tell the user whether he passed the quiz and how many questions he answered correctly.

    $app->get('/end', function () use ($service, $app) {
        $app->render('end.phtml', array(
            'result' => $service->getResult(),
        ));
    });

We do this by getting a `\QuizApp\Service\Quiz\Result` object from the service and passing it to the view.

    <?php if ($result->hasPassed()): ?>
    <h3>You passed!</h3>
    <?php else: ?>
    <h3>You failed!</h3>
    <?php endif; ?>

    <p>You got <?php echo $result->getCorrect(); ?> out of <?php echo $result->getTotal(); ?> questions right.</p>

    <a href="/">Back to quizes</a>

## Writing a Real Mapper with MongoDB

[Explain that we're done and that we can write a real MongoDB mapper here.]

At this point the app is finished--except that we still have to write a real `\QuizApp\Mapper\QuizInterface` instance to connect to MongoDB.

If you haven't already installed MongoDB, run the following command:

    sudo pecl install mongo

Once the install completes it will tell you to add `extension=mongo.so` to your "php.ini" file. You'll then need to restart apache--`sudo service apache2 restart` on Linux.

Now that we've installed MongoDB, we need to create a database, a collection and propogate the collection with a dummy quiz. Run `mongo` and inside the terminal run the following commands:

    > use practicaloop
    > db.quizes.insert({
      title: 'First Quiz', 
      questions: [{
          question: 'Who\'s buried in Grant\'s tomb?',
          solutions: ['Jack', 'Joe', 'Grant', 'Jill'], 
          correctIndex: 2
      }]
    })

Now we need to write another mapper that implements `QuizInterface`.

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

Let's see what's going on here. The class accepts a `\MongoCollection` as a constructor parameter. It then uses the collection to retrieve rows from the database in the `find()` and `findAll()` methods. Both methods follow the same steps: retrieve the row or rows from the database, convert the rows into our `\QuizApp\Entity\Quiz` and `\QuizApp\Entity\Question` objects, and caches them internally to avoid having to look up the same entities later.

All we have left to do is pass an instance of the new mapper to our service in the `index.php` file.

    $service = new \QuizApp\Service\Quiz(
        new \QuizApp\Mapper\Mongo((new \MongoClient)->practicaloop->quizes)
    );

## Conclusion

In this article I showed you how to build an MVC web application using the Service Layer and Domain Model design patterns. By doing so we followed MVC's "fat model, thin controller" rule, keeping our entire controller code to 40 lines. I showed you how to create an implementation-ambilvalent[agian, wrong word] mapper for accessing the database. And we created a service for running the quiz regardless of the user-interface[better word, truing to convey CLI, API, web app, back-end, etc.]. I'll leave it to you to create a command-line version of the application.

---

1: The one I'd recommend is called Doctrine 2.

---

TODO:

+ General:
    + Mention setting up virtual host for site.
    + Changing between "you" and "we" when narrating the code
    + The "create the following" vs. "I've written it and am explaining it to you" conundrum.
+ Rewrite:
    + Introduction 
    + Conclusion
    + Get rid of footnote(?)
    + See inline question about writing out Result object
