<?php
	foreach (glob('private/classes/*.php') as $file) {
		require_once $file;
	}
	include __DIR__.'/router.php';