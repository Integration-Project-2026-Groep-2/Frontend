<?php
	Router::Default('home');

	Router::Add('home','home.php');
	Router::Add('registration','registration.php');

	Router::Finish();