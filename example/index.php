<?php

require 'Db.php';
require '../Vice.php';

$sqlite = new PDO('sqlite:' . __DIR__ . '/database.sq3');

// table structure
// $sqlite->query("CREATE TABLE `users` (
//   `id` INTEGER PRIMARY KEY AUTOINCREMENT,
//   `name` varchar(50) DEFAULT NULL,
//   `emailaddress` varchar(100) DEFAULT NULL
// )");

$db = db($sqlite);

$app = new Vice('/', $db(/* list of tables */) + ['render' => include 'helpers/render.php']);
$app->registerFilter('is:logged', function($server)
{
	//TODO do some login foo
	return 'Yoda';
});

// mount an ajax app on the route /ajax
$app->route('ajax', 'is:logged is:ajax', include 'app/ajax.php');

$app->get('/', 'is:logged', function($render, $isLogged)
{
	echo $render('index.php', [
		'current_user_name' => $isLogged
	]);
});

$app->run();
