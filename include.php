<?php

	require_once __DIR__.'/vendor/autoload.php';
	use Dotenv\Dotenv;

	if (is_file(__DIR__.'/.env')) {
		
		$dotenv = Dotenv::createImmutable(__DIR__);
		$dotenv->load();
	}
	
	include 'private/include.php';