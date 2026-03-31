<?php
	if ($_SERVER['REQUEST_METHOD']=='POST') {
		include __DIR__.'/../../private/send.php';
	}
?>

<form method="POST">
	<input type="text" name="firstName" placeholder="First name" required>*
	<input type="text" name="lastName" placeholder="Last name" required>*
	<input type="email" name="email" placeholder="Email" required>*
	<input type="text" name="phone" placeholder="Phone">
	
	<label>
		<input type="checkbox" name="gdprConsent" value="1" required>
		GDPR Consent
	</label>

	<button type="submit">Register</button>
</form>