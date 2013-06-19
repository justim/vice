<?php

$ajax = new Vice;

// list all users
$ajax->get('users', function($json, $users)
{
	$json($users(/* all */));
});

// get the information about one user
$ajax->get('users/<id>', function($json, $users, $id)
{
	$json($users($id));
});

// delete an user
$ajax->delete('users/<id>', function($json, $id, $users, $post)
{
	$users('delete', $id);
	$json(1);
});

// edit an user
$ajax->put('users/<id>', function($json, $id, $users, $post)
{
	$users($id, $post());
	$json(1);
});

// create an user
$ajax->put('users', function($json, $users, $post)
{
	$users($post());
	$json(1);
});

return $ajax;
