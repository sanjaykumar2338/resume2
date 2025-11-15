<?php
/**
 * Temporary helper to reset the "resume" admin password.
 *
 * Usage:
 * 1. Visit this file in a browser (https://example.com/reset-admin-password.php)
 *    or run `php reset-admin-password.php` from the server.
 * 2. Remove the file immediately after the script reports success.
 */

require_once __DIR__ . '/wp-load.php';

$username   = 'resume';
$new_pass   = 'Resume!2745';
$user       = get_user_by( 'login', $username );

if ( ! $user ) {
	echo "User '{$username}' not found." . PHP_EOL;
	exit( 1 );
}

wp_set_password( $new_pass, $user->ID );

printf(
	"Password reset completed for user '%s'. Use the new password '%s' to log in, then delete this file.\n",
	$username,
	$new_pass
);
