<?php

require 'Db.php';
require '../Vice.php';

// For this example to work you need a database and a table with the following structure
// CREATE TABLE `users` (
//   `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
//   `name` varchar(50) DEFAULT NULL,
//   `emailaddress` varchar(100) DEFAULT NULL,
//   PRIMARY KEY (`id`)
// ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
$db = db(new PDO('mysql:host=localhost;dbname=test', 'root', 'bla'));

$app = new Vice('/lab/vice/example/', $db(/* list of tables */));
$app->registerFilter('is:logged', function($server)
{
	//TODO do some login foo
	return 'Yoda';
});

// mount an ajax app on the route /ajax
$app->route('ajax', 'is:logged is:ajax', include 'app/ajax.php');

$app->get('/', 'is:logged', function($render, $islogged)
{
	echo $render('index.php', [
		'current_user_name' => $islogged
	]);
});

$app->run();
