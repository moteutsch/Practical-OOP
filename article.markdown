# Practical OOP: Creating a Quiz App
## Meta

**Word target**: 1,000-1,600

### Summary

A step-by-step tutorial showing the reader how to create a quiz app, probably with Slim and MongoDB, using correct OOP practices. SOLID principles all, Slim acting only as a thin glue layer.

### App

What do you need in a quiz app? Page for displaying a question with choices, POST action for processing whether correct or incorrect, redirecting to next question and storing result in session and displaying it on the next page, end page.

For this basic feature set, what code do we need?

Top down:

+ Controllers: choose-quiz, solve, check-answer, end
+ Services: {QuizServiceInterface: showAllQuizes, startQuiz, getQuestion, checkSolution, isOver, getResult}
+ Mappers: {QuizMapperInterface: findAll, findById}
+ Entities: {Quiz: __construct(questions), getId, getQuestions; Question: __construct(solutions, correctSolutionId), getId, getSolution, getCorrectSolution, isCorrectSolution}

**Mention**: Abstracting away session for compatability with CLI an other non-web (cookie) interfaces

Extra features: 
+ Pass or fail based on number of correct/incorrect questions (set in database)
+ Simple server-side timer for quiz (time set in database)

### Structure

+ Introduction: why this article is important and what it'll cover
+ Setting up the project: Installing MongoDB, setting up composer and adding Slim as dependency
    curl -sS https://getcomposer.org/installer | php
    
    Install MongoDB "sudo pecl install mongo" and then add "extension=mongo.so" to php.ini as per instructions. Then restart apache "sudo service apache2 restart".
+

## Article Text

If you're 
