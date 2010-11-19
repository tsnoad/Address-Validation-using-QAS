#!/usr/bin/php
<?php

require_once("objects_checkaddr.php");

/**
 * testStartVerify override classes.
 */

// Used to prevent verify_batch() from running.
class testStartVerify extends verifyProcess {
	//fake the activity of verify_batch(), without starting a cascade of functions
	function verify_batch() {
		//this would normally be set as the number of addresses in a given batch
		$this->address_count = 1;
		//the offset would normally be incremented by the chunk_size, when every batch is run
		$this->offset += 1;
	}
}

// Used to fake database queries.
class testStartVerifyModel {
	function get_address_count() {
		//pretend that there's one address to process
		return 1;
	}
}


/**
 * testVerifyBatch override classes.
 */

// Used to prevent verify_address() from running.
class testVerifyBatch extends verifyProcess {
	function verify_address() {
		//do nothing
	}
}

// Used to fake database queries.
class testVerifyBatchModel {
	function get_unclean_addresses() {
		//pretend that there's one address to process
		return array("squibble");
	}
}


/**
 * testVerifyAddress override classes.
 */

// Used to prevent qas_query(), etc. from running.
class testVerifyAddress extends verifyProcess {
	function qas_query($address) {
		//qas_query_return will be set by verifyProcessTest::testVerifyAddress
		return $this->qas_query_return;
	}
	function get_qas_check_code($check) {
		//get_qas_check_code_return will be set by verifyProcessTest::testVerifyAddress
		return $this->get_qas_check_code_return;
	}
	function disect_verified_address($check_lines) {
		//disect_verified_address_return will be set by verifyProcessTest::testVerifyAddress
		return $this->disect_verified_address_return;
	}
}

// Used to fake database queries.
class testVerifyAddressModel {
	function cant_verify($address, $check_code) {
		//cant_verify_called will be set to false by verifyProcessTest::testVerifyAddress
		//now set to true so that we know we've been called
		$this->cant_verify_called = true;
	}
	function can_verify($address, $check_code, $address_verified) {
		//can_verify_called will be set to false by verifyProcessTest::testVerifyAddress
		//now set to true so that we know we've been called
		$this->can_verify_called = true;
	}
}


class verifyProcessTest {
	var $verifyProcess;

	function setUp() {
	}

	function tearDown() {
		unset($this->verifyProcess);
	}

	/**
	 * test verifyProcess::__construct()
	 */
	function testConstruct() {
		$this->verifyProcess = New verifyProcess;
		
		//make sure these variables have been set
		assert(is_string($this->verifyProcess->qas_path));
		assert(is_file($this->verifyProcess->qas_path));
		assert(is_integer($this->verifyProcess->chunk_size));
		assert($this->verifyProcess->chunk_size > 0);
		assert(is_integer($this->verifyProcess->quota));
		assert($this->verifyProcess->quota > 0);
		assert(is_integer($this->verifyProcess->verification_successful));
		assert($this->verifyProcess->verification_successful === 0);
		assert(is_integer($this->verifyProcess->verification_failed));
		assert($this->verifyProcess->verification_failed === 0);
	}

	/**
	 * test verifyProcess::start_verify()
	 */
	function testStartVerify() {
		//use the override class, so that verify_batch() doesn't start a cascade
		$this->verifyProcess = New testStartVerify;

		//get the model override class, so we can pretend we have a database
		$this->verifyProcess->verify_model = New testStartVerifyModel;
		
		//run once, instead of a thousand times
		$this->verifyProcess->chunk_size = 1;
		$this->verifyProcess->quota = 1;
		
		//get testing
		$this->verifyProcess->start_verify();
		
		//number of address we should be processing. our database override should set this to 1
		assert(is_integer($this->verifyProcess->total_addresses));
		assert($this->verifyProcess->total_addresses === 1);

		//this should be set to 1 by verify_batch()
		assert(is_integer($this->verifyProcess->address_count));
		assert($this->verifyProcess->address_count === 1);

		//this should be set to 1 by verify_batch()
		assert(is_integer($this->verifyProcess->offset));
		assert($this->verifyProcess->offset === 1);
	}

	/**
	 * test verifyProcess::verify_batch()
	 */
	function testVerifyBatch() {
		//use the override class, so that verify_address() doesn't start a cascade
		$this->verifyProcess = New testVerifyBatch;
		
		//these would have normally been set by start_verify() and __construct()
		$this->verifyProcess->offset = 0;
		$this->verifyProcess->total_addresses = 1;
		$this->verifyProcess->verification_successful = 0;
		$this->verifyProcess->verification_failed = 0;
		
		//get the model override class, so we can pretend we have a database
		$this->verifyProcess->verify_model = New testVerifyBatchModel;
		
		//get testing
		$this->verifyProcess->verify_batch();
		
		//this should be set to keep track of timing
		assert(is_float($this->verifyProcess->batch_timer));
		assert($this->verifyProcess->batch_timer <> 0);

		//this should be set to 1 by verify_batch()
		assert(is_integer($this->verifyProcess->address_count));
		assert($this->verifyProcess->address_count > 0);

		//this should be set to 1 by verify_batch()
		assert(is_integer($this->verifyProcess->offset));
		assert($this->verifyProcess->offset > 0);
	}

	/**
	 * test verifyProcess::verify_address()
	 */
	function testVerifyAddress() {
		//use the override class, so that qas_query(), etc. don't run. we'll test them later ^_^
		$this->verifyProcess = New testVerifyAddress;

		//what we'll pretend qas_query() has returned
		$this->verifyProcess->qas_query_return = "R123\nSQUIGGLE BOP\nEXTRA DATA";
		//what we'll pretend get_qas_check_code() has returned
		$this->verifyProcess->get_qas_check_code_return = array(array("SQUIGGLE BOP", "EXTRA DATA"), "R123");
		//what we'll pretend disect_verified_address() has returned
		$this->verifyProcess->disect_verified_address_return = array("address" => "EXTRA DATA\nSQUIGGLE BOP");
		
		//get the model override class, so we can pretend we have a database
		$this->verifyProcess->verify_model = New testVerifyAddressModel;
		//this will be set to true if cant_verify() is called
		$this->verifyProcess->verify_model->cant_verify_called = false;
		//this will be set to true if can_verify() is called
		$this->verifyProcess->verify_model->can_verify_called = false;
		
		//get testing
		$this->verifyProcess->verify_address("EXTRA DATA, SQUIGGLE BOP");
		
		//cant_verify() SHOULD NOT have been called
		assert($this->verifyProcess->verify_model->cant_verify_called === false);
		//can_verify() should have been called
		assert($this->verifyProcess->verify_model->can_verify_called === true);

		//verification_successful counter should have been incremented
		assert(is_integer($this->verifyProcess->verification_successful));
		assert($this->verifyProcess->verification_successful > 0);

		//verification_failed counter SHOULD NOT have been incremented
		assert(is_integer($this->verifyProcess->verification_failed));
		assert($this->verifyProcess->verification_failed === 0);
	}

	/**
	 * test verifyProcess::verify_address()
	 *
	 * This time we'll pretend that qas_query() has returned absolutly nothing.
	 */
	function testVerifyAddressFail1() {
		//use the override class, so that qas_query(), etc. don't run. we'll test them later ^_^
		$this->verifyProcess = New testVerifyAddress;

		//what we'll pretend qas_query() has returned
		$this->verifyProcess->qas_query_return = "";
		//what we'll pretend get_qas_check_code() has returned
		$this->verifyProcess->get_qas_check_code_return = array(array("SQUIGGLE BOP", "EXTRA DATA"), "R123");
		//what we'll pretend disect_verified_address() has returned
		$this->verifyProcess->disect_verified_address_return = array("address" => "EXTRA DATA\nSQUIGGLE BOP");
		
		//get the model override class, so we can pretend we have a database
		$this->verifyProcess->verify_model = New testVerifyAddressModel;
		//this will be set to true if cant_verify() is called
		$this->verifyProcess->verify_model->cant_verify_called = false;
		//this will be set to true if can_verify() is called
		$this->verifyProcess->verify_model->can_verify_called = false;
		
		//get testing
		$this->verifyProcess->verify_address("EXTRA DATA, SQUIGGLE BOP");
		
		//can_verify() should have been called
		assert($this->verifyProcess->verify_model->cant_verify_called === true);
		//can_verify() SHOULD NOT have been called
		assert($this->verifyProcess->verify_model->can_verify_called === false);

		//verification_successful counter SHOULD NOT have been incremented
		assert(is_integer($this->verifyProcess->verification_successful));
		assert($this->verifyProcess->verification_successful === 0);

		//verification_failed counter should have been incremented
		assert(is_integer($this->verifyProcess->verification_failed));
		assert($this->verifyProcess->verification_failed > 0);
	}

	/**
	 * test verifyProcess::verify_address()
	 *
	 * This time we'll pretend that qas_query() has a said the address could not be verified.
	 */
	function testVerifyAddressFail2() {
		//use the override class, so that qas_query(), etc. don't run. we'll test them later ^_^
		$this->verifyProcess = New testVerifyAddress;

		//what we'll pretend qas_query() has returned
		$this->verifyProcess->qas_query_return = "Z123\nSQUIGGLE BOP\nEXTRA DATA";
		//what we'll pretend get_qas_check_code() has returned
		$this->verifyProcess->get_qas_check_code_return = array(array("SQUIGGLE BOP", "EXTRA DATA"), "Z123");
		//what we'll pretend disect_verified_address() has returned
		$this->verifyProcess->disect_verified_address_return = array("address" => "EXTRA DATA\nSQUIGGLE BOP");
		
		//get the model override class, so we can pretend we have a database
		$this->verifyProcess->verify_model = New testVerifyAddressModel;
		//this will be set to true if cant_verify() is called
		$this->verifyProcess->verify_model->cant_verify_called = false;
		//this will be set to true if can_verify() is called
		$this->verifyProcess->verify_model->can_verify_called = false;
		
		//get testing
		$this->verifyProcess->verify_address("EXTRA DATA, SQUIGGLE BOP");
		
		//can_verify() should have been called
		assert($this->verifyProcess->verify_model->cant_verify_called === true);
		//can_verify() SHOULD NOT have been called
		assert($this->verifyProcess->verify_model->can_verify_called === false);

		//verification_successful counter SHOULD NOT have been incremented
		assert(is_integer($this->verifyProcess->verification_successful));
		assert($this->verifyProcess->verification_successful === 0);

		//verification_failed counter should have been incremented
		assert(is_integer($this->verifyProcess->verification_failed));
		assert($this->verifyProcess->verification_failed > 0);
	}

	/**
	 * test verifyProcess::verify_address()
	 *
	 * This time we'll pretend that qas_query() has a said the address could be verified, but hasn't returned an address.
	 */
	function testVerifyAddressFail3() {
		//use the override class, so that qas_query(), etc. don't run. we'll test them later ^_^
		$this->verifyProcess = New testVerifyAddress;

		//what we'll pretend qas_query() has returned
		$this->verifyProcess->qas_query_return = "R123";
		//what we'll pretend get_qas_check_code() has returned
		$this->verifyProcess->get_qas_check_code_return = array(array(), "R123");
		//what we'll pretend disect_verified_address() has returned
		$this->verifyProcess->disect_verified_address_return = array("address" => "");
		
		//get the model override class, so we can pretend we have a database
		$this->verifyProcess->verify_model = New testVerifyAddressModel;
		//this will be set to true if cant_verify() is called
		$this->verifyProcess->verify_model->cant_verify_called = false;
		//this will be set to true if can_verify() is called
		$this->verifyProcess->verify_model->can_verify_called = false;
		
		//get testing
		$this->verifyProcess->verify_address("EXTRA DATA, SQUIGGLE BOP");
		
		//can_verify() should have been called
		assert($this->verifyProcess->verify_model->cant_verify_called === true);
		//can_verify() SHOULD NOT have been called
		assert($this->verifyProcess->verify_model->can_verify_called === false);

		//verification_successful counter SHOULD NOT have been incremented
		assert(is_integer($this->verifyProcess->verification_successful));
		assert($this->verifyProcess->verification_successful === 0);

		//verification_failed counter should have been incremented
		assert(is_integer($this->verifyProcess->verification_failed));
		assert($this->verifyProcess->verification_failed > 0);
	}

	/**
	 * test verifyProcess::verify_address()
	 *
	 * This time we'll pretend that qas_query() has a said the address could be verified, but hasn't returned an address.
	 */
	function testVerifyAddressFail4() {
		//use the override class, so that qas_query(), etc. don't run. we'll test them later ^_^
		$this->verifyProcess = New testVerifyAddress;

		//what we'll pretend qas_query() has returned
		$this->verifyProcess->qas_query_return = "R123\n\n";
		//what we'll pretend get_qas_check_code() has returned
		$this->verifyProcess->get_qas_check_code_return = array(array("", ""), "R123");
		//what we'll pretend disect_verified_address() has returned
		$this->verifyProcess->disect_verified_address_return = array("address" => "\n");
		
		//get the model override class, so we can pretend we have a database
		$this->verifyProcess->verify_model = New testVerifyAddressModel;
		//this will be set to true if cant_verify() is called
		$this->verifyProcess->verify_model->cant_verify_called = false;
		//this will be set to true if can_verify() is called
		$this->verifyProcess->verify_model->can_verify_called = false;
		
		//get testing
		$this->verifyProcess->verify_address("EXTRA DATA, SQUIGGLE BOP");
		
		//can_verify() should have been called
		assert($this->verifyProcess->verify_model->cant_verify_called === true);
		//can_verify() SHOULD NOT have been called
		assert($this->verifyProcess->verify_model->can_verify_called === false);

		//verification_successful counter SHOULD NOT have been incremented
		assert(is_integer($this->verifyProcess->verification_successful));
		assert($this->verifyProcess->verification_successful === 0);

		//verification_failed counter should have been incremented
		assert(is_integer($this->verifyProcess->verification_failed));
		assert($this->verifyProcess->verification_failed > 0);
	}

	/**
	 * test verifyProcess::qas_query()
	 */
	function testQASQuery() {
		$this->verifyProcess = New verifyProcess;
		
		//we'll try to verify this address, since we already know what it'll come out as
		$address = array(
			"address" => "EXTRA LINES, 11 national cct",
			"suburb" => "barton",
			"state" => "act",
			"postcode" => "2600"
		);
		
		//get testing
		$return = $this->verifyProcess->qas_query($address);
		
		//the result should be trimmed
		assert($return == trim($return));
		
		//split the result by newlines, to make it easier to check
		$return_array = explode("\n", $return);
		
		//since we've given it a valid address, the first letter of the check code should be R
		assert(substr($return_array[0], 0, 1) == "R");
		//the check code, ignoring the first letter, should be 27 digits long and numeric
		assert(is_numeric(substr($return_array[0], 1, 27)));

		//the returned address should look exacly like this
		assert($return_array[1] == "11 National Cct");
		assert($return_array[2] == "");
		assert($return_array[3] == "BARTON  ACT  2600");
		assert($return_array[4] == "AUS");
		assert($return_array[5] == "EXTRA LINES");
	}

	/**
	 * test verifyProcess::get_qas_check_code()
	 */
	function testGetQASCheckCode() {
		$this->verifyProcess = New verifyProcess;
		
		//this is what get_qas_check_code() would normally be given
		$check = "R913000000000000000000000000\n";
		$check .= "11 National Cct\n";
		$check .= "\n";
		$check .= "BARTON  ACT  2600\n";
		$check .= "AUS\n";
		$check .= "EXTRA LINES";
		
		//get testing
		$return = $this->verifyProcess->get_qas_check_code($check);
		
		//we should get an array of address parts
		assert($return[0] == array("11 National Cct", "", "BARTON  ACT  2600",  "AUS", "EXTRA LINES"));
		//and the check code
		assert($return[1] == "R913000000000000000000000000");
	}

	/**
	 * test verifyProcess::disect_verified_address()
	 */
	function testDisectVerifiedAddress() {
		$this->verifyProcess = New verifyProcess;
		
		//this is what disect_verified_address() would normally be given
		$check_lines = array("11 National Cct", "", "BARTON  ACT  2600",  "AUS", "EXTRA LINES");
		
		//get testing
		$return = $this->verifyProcess->disect_verified_address($check_lines);
		
		//address parts should be returned in an array
		assert(is_string($return['address']));
		assert($return['address'] == "EXTRA LINES\n11 National Cct\n");
		assert(is_string($return['suburb']));
		assert($return['suburb'] == "BARTON");
		assert(is_string($return['state']));
		assert($return['state'] == "ACT");
		assert(is_string($return['postcode']));
		assert($return['postcode'] == "2600");
		assert(is_string($return['country']));
	}
}

//start output buffering so we don't see all the crap that would normally be echod
//errors and warning and stuff will still show up
ob_start();

//get the testing method
$test = New verifyProcessTest;

//start tests
$test->testConstruct();
$test->tearDown();

$test->testStartVerify();
$test->tearDown();

$test->testVerifyBatch();
$test->tearDown();

$test->testVerifyAddress();
$test->tearDown();

$test->testVerifyAddressFail1();
$test->tearDown();

$test->testVerifyAddressFail2();
$test->tearDown();

$test->testVerifyAddressFail3();
$test->tearDown();

$test->testVerifyAddressFail4();
$test->tearDown();

$test->testQASQuery();
$test->tearDown();

$test->testGetQASCheckCode();
$test->tearDown();

$test->testDisectVerifiedAddress();
$test->tearDown();

//stop output buffering
ob_clean();

//where there any errors?
if (!error_get_last()) {
	echo "test OK.\n";

} else {
	echo "test FAIL.\n";
}

?>