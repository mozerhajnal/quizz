<?php

$routes = [
    'GET' =>[
        '/'              =>  'homeHandler',
        '/start-quiz'    =>  'loadQuizHandler',
        '/end-quiz'      =>  'endQuizHandler',
        '/admin'         =>  'addQuizHandler'
    ],
    'POST' =>[
        '/submit-answer' => 'submitAnswerHandler',
        '/create-quiz'   => 'createQuizHandler'
    ]

];

$url = $_SERVER['REQUEST_URI'];
$path =parse_url($url)['path'];
$method = $_SERVER['REQUEST_METHOD'];

$handler = $routes[$method][$path]??'';
if($handler && is_callable($handler)){
    $conn = new mysqli('localhost','root','','quizzer',3306);
    $conn -> set_charset('utf8');
    $handler($conn,$_GET,$_POST);
}else{
    echo '404';
}

function homeHandler(mysqli $conn,$query,$body)
{
    $result = $conn->query('SELECT * FROM questions');
    $total = $result->num_rows;

    require 'home.phtml';

}


function loadQuizHandler(mysqli $conn,$query,$body)
{

    $queryString = "SELECT * FROM questions WHERE question_number=?";
    $statement = $conn->prepare($queryString);

    $number = $query['n'];

    $statement->bind_param('s',$number);
    $statement->execute();

    $question = $statement->get_result()->fetch_assoc();

    $queryString2 = "SELECT * FROM choices WHERE question_number=?";
    $statement2 = $conn->prepare($queryString2);

    $statement2->bind_param('s',$number);
    $statement2->execute();
    $result = $statement2->get_result();

    $choices = [];
    while($data = $result->fetch_assoc()){
        $choices[] = $data;
    }

    $result = $conn->query('SELECT * FROM questions');
    $total = $result->num_rows;


    require 'question.phtml';

}

function submitAnswerHandler (mysqli $conn,$query,$body)
{

    session_start();

    if(!isset($_SESSION['score'])){
        $_SESSION['score'] = 0;
    }


    $number = $body['number'];
    $selected_choice = $body['choice'];
    $next = $number + 1;

    $queryString = "SELECT * FROM choices WHERE question_number = ? and is_correct = 1";
    $statement = $conn->prepare($queryString);

    $statement->bind_param('s',$number);
    $statement->execute();

    $result = $statement->get_result()->fetch_assoc();
    $correct_choice = $result['id'];

    if($correct_choice == $selected_choice){
        $_SESSION['score']++;
        var_dump($_SESSION['score']);
    }

    $result = $conn->query('SELECT * FROM questions');
    $total = $result->num_rows;

    if($number == $total){
        header('Location: /end-quiz');
        exit();
    }else{
        header ('Location: /start-quiz?n='.$next);
    }

}

function endQuizHandler(mysqli $conn,$query,$body)
{
    session_start();
    session_destroy();

    require 'final.phtml';
}

function addQuizHandler(mysqli $conn,$query,$body)
{
    $result = $conn->query('SELECT * FROM questions');
    $total = $result->num_rows;
    $next = $total+1;

    require 'admin.phtml'; 
}


function createQuizHandler(mysqli $conn,$query,$body)
{
    $question_number = $body['question_number'];
    $question_text = $body['question_text'];
    $correct_choice = $body['correct_choice'];

    $choices = [];
    $choices[1] = $body['choice1'];
    $choices[2] = $body['choice2'];
    $choices[3] = $body['choice3'];
    $choices[4] = $body['choice4'];
    $choices[5] = $body['choice5'];

    $queryString = "INSERT INTO `questions`(`question_number`, `text`) VALUES (?,?)";
    $stmt = $conn ->prepare($queryString);

    $stmt -> bind_param('ss',$question_number,$question_text);
    $isSuccess = $stmt->execute();

    if($isSuccess){
        foreach($choices as $choice => $value)
        {
            if($value != ''){
                if($correct_choice == $choice){
                    $is_correct = 1;
                }else{
                    $is_correct = 0;
                }

                $queryString2 = "INSERT INTO `choices`(`question_number`, `is_correct`, `text`) VALUES (?,?,?)";
                $stmt2 = $conn ->prepare($queryString2);

                $stmt2 ->bind_param('sis',$question_number,$is_correct,$value);
                $stmt2->execute();
            }
        }

    header('Location: /');

    }

}