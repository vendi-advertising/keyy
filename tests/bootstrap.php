<?php
/**
 * PHPUnit bootstrap file
 *
 * @package Vendi_Wordfence
 */

$_tests_dir = dirname( __DIR__ ) . '/vendor/WordPress/wordpress-develop/tests/phpunit/';

// require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

require_once dirname( dirname( __FILE__ ) ) .  '/keyy.php';
