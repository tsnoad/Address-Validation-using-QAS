<?php
	//we require at least and address
	if (empty($_GET['address']) && empty($_GET['address2'])) {
		die();
	}

	//and a suburb
	if (empty($_GET['suburb'])) {
		die();
	}

	$address['address'] = $_GET['address'].",".$_GET['address2'];
	$address['suburb'] = $_GET['suburb'];
	$address['state'] = $_GET['state'];
	$address['postcode'] = $_GET['postcode'];

	require_once("../objects_checkaddr.php");

	$verifyProcess = New verifyProcess;

	//verify the address
	$check = $verifyProcess->qas_query($address);

	//if we didn't get anything back from the verifier
	if (empty($check)) {
		die();
	}

	//get the check code and everything else from the data the verifier returned
	list($check_lines, $check_code) = $verifyProcess->get_qas_check_code($check);

	//if the check code doesn't start with R, then verification failed
	//or if the verifier didn't return an address
	if (substr($check_code, 0, 1) != "R" || empty($check_lines)) {
		die();
	}

	//disect the address into street, suburb, state, etc. and put it into an array
	$address_verified = $verifyProcess->disect_verified_address($check_lines);

	//return the verified address and the check code as a JSON string
	echo json_encode(array_merge((array)$address_verified, (array)array("check_code" => $check_code)));

?>