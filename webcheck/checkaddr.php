<?php
	$address = $_GET['address'];
	$address2 = $_GET['address2'];
	$suburb = $_GET['suburb'];
	$state = $_GET['state'];
	$postcode = $_GET['postcode'];

/*
	if (empty($address)) {
		die();
	}
*/

	$check_address = $address.", ".$address2.", ".$suburb.", ".$state.", ".$postcode;

/* 	$check_address = "11 national cct, barton, act, 2600"; */

	$oldcwd = getcwd();
	chdir("/home/TSnoad/checkaddr/");
	$check = shell_exec("/home/TSnoad/checkaddr/qas_startup ".escapeshellarg($check_address)."");
	chdir($oldcwd);

	$check = trim($check);

	$check_lines = explode("\n", $check);

	$verified['check_code'] = array_shift($check_lines);

	$address_notused = array_slice($check_lines, 4);
	$check_suburb_state_postcode = explode("  ", $check_lines[2]);

	$verified['address'] = $check_lines[0]."\n".$check_lines[1];
	if (!empty($address_notused)) $verified['address'] = implode("\n", $address_notused)."\n".$verified['address'];

	$verified['suburb'] = $check_suburb_state_postcode[0];
	$verified['state'] = $check_suburb_state_postcode[1];
	$verified['postcode'] = $check_suburb_state_postcode[2];


	echo json_encode($verified)

?>