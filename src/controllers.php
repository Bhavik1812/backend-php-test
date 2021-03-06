<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as assert; // The ValidatorServiceProvider provides a service for validating data

$app['twig'] = $app->share($app->extend('twig', function($twig, $app) {
    $twig->addGlobal('user', $app['session']->get('user'));

    return $twig;
}));


$app->get('/', function () use ($app) {
    return $app['twig']->render('index.html', [
        'readme' => file_get_contents('README.md'),
    ]);
});


$app->match('/login', function (Request $request) use ($app) {
    $username = $request->get('username');
    $password = $request->get('password');

    if ($username) {
        $sql = "SELECT * FROM users WHERE username = '$username' and password = '$password'";
        $user = $app['db']->fetchAssoc($sql);

        if ($user){
            $app['session']->set('user', $user);
            return $app->redirect('/todo');
        }
          else {
           $app['session']->getFlashBag()->add('alert', 'Incorrect Username and/or Password');
        }
    }

    return $app['twig']->render('login.html', array());
});


$app->get('/logout', function () use ($app) {
    $app['session']->set('user', null);
    return $app->redirect('/');
});


$app->get('/todo/{id}', function (Request $request,$id) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }

    if ($id){
        $sql = "SELECT * FROM todos WHERE id = '$id'";
        $todo = $app['db']->fetchAssoc($sql);

        return $app['twig']->render('todo.html', [
            'todo' => $todo,
        ]);
    } else {
        $no_row=4; // # of rows per page for pagination
        $sql = "SELECT * FROM todos WHERE user_id = '${user['id']}'";
        $all_todos = count($app['db']->fetchAll($sql));
        $nopage= ceil($all_todos/$no_row);
        $page_no= $request->get('p') ?: '1';
        $offset= ($page_no-1)*$no_row;
        $sql = "SELECT * FROM todos WHERE user_id = '${user['id']}' LIMIT $no_row OFFSET $offset";
        $todos = $app['db']->fetchAll($sql);
        return $app['twig']->render('todos.html', [
            'todos' => $todos,
            'nopage' => $nopage,
             'page_no' => $page_no,
        ]);
    }
})
->value('id', null);

$app->get('/todo/{id}/json', function ($id) use ($app) {
     if (null === $user = $app['session']->get('user')) {
         return $app->redirect('/login');
     }

     // if we have a valid id then retreve the data and display it as json
     if ($id) {
         $sql = "SELECT * FROM todos WHERE id = '$id'";
         $todo = $app['db']->fetchAssoc($sql);
         return $app->json($todo);
     }

 })
 ->value('id', null);
$app->post('/todo/add', function (Request $request) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }

    $user_id = $user['id'];
    $description = $request->get('description');

    // validator is added to check whether the description field is empty or not
    //if description is not empty then only it add the record.
    $descriptionerror = $app['validator']->validate($description,new assert\NotBlank());
    if(!count($descriptionerror))
    {
      $sql="INSERT INTO todos (user_id,description) VALUES ('$user_id','$description')";
      $app['db']->executeUpdate($sql);
        $app['session']->getFlashBag()->add('alert', 'Task added successfully!');
    }
    else {
      //This alert messege will pop up when description feild is empty
      $app['session']->getFlashBag()->add('alert','Description feild should not be empty !');
    }
    return $app->redirect('/todo');
});
$app->match('/todo/done/{id}', function ($id) use ($app) {
 //this will mark the task as completed
   $sql = "UPDATE `todos` SET `completed` = !completed WHERE `todos`.`id` = '$id'";
  $app['db']->executeUpdate($sql);
    return $app->redirect('/todo');
});

$app->match('/todo/delete/{id}', function ($id) use ($app) {

    $sql = "DELETE FROM todos WHERE id = '$id'";
    $app['db']->executeUpdate($sql);
$app['session']->getFlashBag()->add('alert', 'Task removed successfully!');
    return $app->redirect('/todo');
});
