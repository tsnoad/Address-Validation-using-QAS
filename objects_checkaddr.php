<?php

/**
 * Class for logging function.
 * All classes extend this class, so that they have access to systemController::logger()
 */
class systemController {
	// 1: errors only
	// 2: errors and progress messages only
	// 3: total banality
	const log_level = 2;

	/**
	 * Log an error message.
	 */
	function logger($message, $level) {
		if ($level <= systemController::log_level) {
			if ($level === 1) {
				echo "\nERROR: "; 
			}

			echo $message."\n";
		}
	}
}

/**
 * Database class.
 */
class initDB extends systemController {
	/**
	 * Run a query.
	 * DB name is set when the class is called.
	 */
	function query($query) {
		//authentication and logging need to be fixed
		$conn = pg_connect("dbname={$this->dbname} user=TSnoad");
		$result = pg_query($conn, $query);
		$return = pg_fetch_all($result);
		pg_close($conn);
	
		return $return;
	}
}

/**
 * Model for authentication database.
 * Used to verify addresses that have never been verified previously.
 */
class authenticationAddresses extends systemController {
	/*
		-- Table to hold the verified address for the authentication database
		CREATE TABLE address_verified (
		  sys_address_verified_id BIGSERIAL PRIMARY KEY,
		  sys_address_id BIGINT,
		  address_end_date TIMESTAMP,
		  address TEXT,
		  suburb TEXT,
		  state TEXT,
		  postcode TEXT,
		  country TEXT,
		  return_code TEXT,
		  verifiable BOOLEAN DEFAULT FALSE,
		  address_verified TEXT,
		  suburb_verified TEXT,
		  state_verified TEXT,
		  postcode_verified TEXT,
		  country_verified TEXT,
		  FOREIGN KEY (sys_address_id) REFERENCES address(sys_address_id) MATCH FULL ON UPDATE CASCADE ON DELETE CASCADE
		);	
	*/

	/**
	 * Get ready to verify addresses.
	 */
	function __construct() {
		parent::logger("\nPreparing to verify addresses in authentication.", 2);

		//so we can connect to the database
		$this->db = New initDB;
		$this->db->dbname = "ea_mart_auth";

		//this class is where all the action will be taking place
		$verify_process = New verifyProcess;

		//so that this model will have access to variables in verifyProcess
		$this->verify_process = $verify_process;

		//so that verifyProcess will have access to function in this model
		$verify_process->verify_model = $this;

		//start the verifing
		$verify_process->start_verify();
	}

	/**
	 * How many addresses need verifing?
	 */
	function get_address_count() {
		$address_count_query = $this->db->query("SELECT count(a.sys_address_id) FROM address a LEFT JOIN address_verified v ON (a.sys_address_id=v.sys_address_id) WHERE v.sys_address_id IS NULL AND a.country='AUSTRALIA' AND a.end_date='infinity';");
		
		return $address_count_query[0]['count'];
	}

	/**
	 * Get a chunk of addresses that need verifing.
	 */
	function get_unclean_addresses() {
		$unclean_addresses = $this->db->query("SELECT a.* FROM address a LEFT JOIN address_verified v ON (a.sys_address_id=v.sys_address_id) WHERE v.sys_address_id IS NULL AND a.country='AUSTRALIA' AND a.end_date='infinity' LIMIT {$this->verify_process->chunk_size} OFFSET {$this->verify_process->offset};");

		return $unclean_addresses;
	}

	/**
	 * What to save to the database if an address can't be verified.
	 */
	function cant_verify($address, $check_code) {
		$this->db->query("
			INSERT INTO address_verified (
			  sys_address_id,
			  address,
			  suburb,
			  state,
			  postcode,
			  country,
			  return_code,
			  verifiable
			)
			VALUES (
			  '".pg_escape_string($address['sys_address_id'])."',
			  '".pg_escape_string($address['address'])."',
			  '".pg_escape_string($address['suburb'])."',
			  '".pg_escape_string($address['state'])."',
			  '".pg_escape_string($address['postcode'])."',
			  '".pg_escape_string($address['country'])."',
			  '".pg_escape_string($check_code)."',
			  FALSE
			);
		");
	}

	/**
	 * What to save to the database when we've verified an address.
	 */
	function can_verify($address, $check_code, $address_verified) {
		$this->db->query("
			INSERT INTO address_verified (
			  sys_address_id,
			  address,
			  suburb,
			  state,
			  postcode,
			  country,
			  return_code,
			  verifiable,
			  address_verified,
			  suburb_verified,
			  state_verified,
			  postcode_verified,
			  country_verified
			)
			VALUES (
			  '".pg_escape_string($address['sys_address_id'])."',
			  '".pg_escape_string($address['address'])."',
			  '".pg_escape_string($address['suburb'])."',
			  '".pg_escape_string($address['state'])."',
			  '".pg_escape_string($address['postcode'])."',
			  '".pg_escape_string($address['country'])."',
			  '".pg_escape_string($check_code)."',
			  TRUE,
			  '".pg_escape_string($address_verified['address'])."',
			  '".pg_escape_string($address_verified['suburb'])."',
			  '".pg_escape_string($address_verified['state'])."',
			  '".pg_escape_string($address_verified['postcode'])."',
			  '".pg_escape_string($address_verified['country'])."'
			);
		");
	}
}

/**
 * Model for authentication database.
 * Used to verify addresses that have been verified previously, but have changed and need to be verified again.
 */
class authenticationAddressesModified extends systemController {

	/**
	 * Get ready to verify addresses.
	 */
	function __construct() {
		parent::logger("\nPreparing to verify modified addresses in authentication.", 2);

		//so we can connect to the database
		$this->db = New initDB;
		$this->db->dbname = "ea_mart_auth";

		//this class is where all the action will be taking place
		$verify_process = New verifyProcess;

		//so that this model will have access to variables in verifyProcess
		$this->verify_process = $verify_process;

		//so that verifyProcess will have access to function in this model
		$verify_process->verify_model = $this;

		//start the verifing
		$verify_process->start_verify();
	}

	/**
	 * How many addresses need verifing?
	 */
	function get_address_count() {
		$address_count_query = $this->db->query("SELECT count(a.sys_address_id) FROM address a LEFT JOIN address_verified v ON (a.sys_address_id=v.sys_address_id) WHERE (v.address!=a.address OR v.suburb!=a.suburb OR v.state!=a.state OR v.postcode!=a.postcode OR v.country!=a.country) AND a.country='AUSTRALIA' AND a.end_date='infinity';");
		
		return $address_count_query[0]['count'];
	}

	/**
	 * Get a chunk of addresses that need verifing.
	 */
	function get_unclean_addresses() {
		$unclean_addresses = $this->db->query("SELECT v.sys_address_verified_id, a.* FROM address a LEFT JOIN address_verified v ON (a.sys_address_id=v.sys_address_id) WHERE (v.address!=a.address OR v.suburb!=a.suburb OR v.state!=a.state OR v.postcode!=a.postcode OR v.country!=a.country) AND a.country='AUSTRALIA' AND a.end_date='infinity' LIMIT {$this->verify_process->chunk_size} OFFSET {$this->verify_process->offset};");

		return $unclean_addresses;
	}

	/**
	 * What to save to the database if an address can't be verified.
	 */
	function cant_verify($address, $check_code) {
		$this->db->query("
			UPDATE address_verified
			SET
			  address='".pg_escape_string($address['address'])."',
			  suburb='".pg_escape_string($address['suburb'])."',
			  state='".pg_escape_string($address['state'])."',
			  postcode='".pg_escape_string($address['postcode'])."',
			  country='".pg_escape_string($address['country'])."',
			  return_code='".pg_escape_string($check_code)."',
			  verifiable=FALSE,
			  address_verified='',
			  suburb_verified='',
			  state_verified='',
			  postcode_verified='',
			  country_verified=''
			WHERE
			  sys_address_verified_id='".pg_escape_string($address['sys_address_verified_id'])."'
			;
		");
	}

	/**
	 * What to save to the database when we've verified an address.
	 */
	function can_verify($address, $check_code, $address_verified) {
		$this->db->query("
			UPDATE address_verified
			SET
			  address='".pg_escape_string($address['address'])."',
			  suburb='".pg_escape_string($address['suburb'])."',
			  state='".pg_escape_string($address['state'])."',
			  postcode='".pg_escape_string($address['postcode'])."',
			  country='".pg_escape_string($address['country'])."',
			  return_code='".pg_escape_string($check_code)."',
			  verifiable=TRUE,
			  address_verified='".pg_escape_string($address_verified['address'])."',
			  suburb_verified='".pg_escape_string($address_verified['suburb'])."',
			  state_verified='".pg_escape_string($address_verified['state'])."',
			  postcode_verified='".pg_escape_string($address_verified['postcode'])."',
			  country_verified='".pg_escape_string($address_verified['country'])."'
			WHERE
			  sys_address_verified_id='".pg_escape_string($address['sys_address_verified_id'])."'
			;
		");
	}
}

/**
 * Model for membership database.
 * Used to verify addresses that have never been verified previously.
 */
class membershipAddresses extends systemController {
	/*
		-- Table to hold the verified address for the membership database
		CREATE TABLE contact_verified (
		  sys_contact_verified_id BIGSERIAL PRIMARY KEY,
		  sys_contact_id BIGINT,
		  address_end_date TIMESTAMP,
		  contact TEXT,
		  suburb TEXT,
		  state TEXT,
		  postcode TEXT,
		  country TEXT,
		  return_code TEXT,
		  verifiable BOOLEAN DEFAULT FALSE,
		  contact_verified TEXT,
		  suburb_verified TEXT,
		  state_verified TEXT,
		  postcode_verified TEXT,
		  country_verified TEXT,
		  FOREIGN KEY (sys_contact_id) REFERENCES contact(sys_contact_id) MATCH FULL ON UPDATE CASCADE ON DELETE CASCADE
		);	
	*/

	/**
	 * Get ready to verify addresses.
	 */
	function __construct() {
		parent::logger("\nPreparing to verify addresses in membership.", 2);

		//so we can connect to the database
		$this->db = New initDB;
		$this->db->dbname = "ea_mart_iea_membership";

		//this class is where all the action will be taking place
		$verify_process = New verifyProcess;

		//so that this model will have access to variables in verifyProcess
		$this->verify_process = $verify_process;

		//so that verifyProcess will have access to function in this model
		$verify_process->verify_model = $this;

		//start the verifing
		$verify_process->start_verify();
	}

	/**
	 * How many addresses need verifing?
	 */
	function get_address_count() {
		$address_count_query = $this->db->query("SELECT count(a.sys_contact_id) FROM contact a LEFT JOIN contact_verified v ON (a.sys_contact_id=v.sys_contact_id) WHERE v.sys_contact_id IS NULL AND a.contact_type='Address' AND a.country='AUSTRALIA' AND a.end_date='infinity';");
		
		return $address_count_query[0]['count'];
	}

	/**
	 * Get a chunk of addresses that need verifing.
	 */
	function get_unclean_addresses() {
		$unclean_addresses = $this->db->query("SELECT a.contact as address, a.* FROM contact a LEFT JOIN contact_verified v ON (a.sys_contact_id=v.sys_contact_id) WHERE v.sys_contact_id IS NULL AND a.contact_type='Address' AND a.country='AUSTRALIA' AND a.end_date='infinity' LIMIT {$this->verify_process->chunk_size} OFFSET {$this->verify_process->offset};");

		return $unclean_addresses;
	}

	/**
	 * What to save to the database if an address can't be verified.
	 */
	function cant_verify($address, $check_code) {
		$this->db->query("
			INSERT INTO contact_verified (
			  sys_contact_id,
			  contact,
			  suburb,
			  state,
			  postcode,
			  country,
			  return_code,
			  verifiable
			)
			VALUES (
			  '".pg_escape_string($address['sys_contact_id'])."',
			  '".pg_escape_string($address['address'])."',
			  '".pg_escape_string($address['suburb'])."',
			  '".pg_escape_string($address['state'])."',
			  '".pg_escape_string($address['postcode'])."',
			  '".pg_escape_string($address['country'])."',
			  '".pg_escape_string($check_code)."',
			  FALSE
			);
		");
	}

	/**
	 * What to save to the database when we've verified an address.
	 */
	function can_verify($address, $check_code, $address_verified) {
		$this->db->query("
			INSERT INTO contact_verified (
			  sys_contact_id,
			  contact,
			  suburb,
			  state,
			  postcode,
			  country,
			  return_code,
			  verifiable,
			  contact_verified,
			  suburb_verified,
			  state_verified,
			  postcode_verified,
			  country_verified
			)
			VALUES (
			  '".pg_escape_string($address['sys_contact_id'])."',
			  '".pg_escape_string($address['address'])."',
			  '".pg_escape_string($address['suburb'])."',
			  '".pg_escape_string($address['state'])."',
			  '".pg_escape_string($address['postcode'])."',
			  '".pg_escape_string($address['country'])."',
			  '".pg_escape_string($check_code)."',
			  TRUE,
			  '".pg_escape_string($address_verified['address'])."',
			  '".pg_escape_string($address_verified['suburb'])."',
			  '".pg_escape_string($address_verified['state'])."',
			  '".pg_escape_string($address_verified['postcode'])."',
			  '".pg_escape_string($address_verified['country'])."'
			);
		");
	}
}

/**
 * Verify some addresses!
 * Run as a method from one of the models. Does all the work.
 */
class verifyProcess extends systemController {
	/**
	 * Set up variables that will be used later.
	 */
	function __construct() {
		//path to the compiled C script that does the verifing
		$this->qas_path = "/home/TSnoad/checkaddr/qas_startup";

		//how many addresses in a batch
		$this->chunk_size = 100;

		//maximum number of addresses to verify
		$this->quota = 1000;

		//used to count how many verifications succeeded and failed
		$this->verification_successful = 0;
		$this->verification_failed = 0;
	}

	/**
	 * Start the verifing. Called from __contruct() in a model.
	 */
	function start_verify() {
		//how many addresses need to be verified?
		$this->total_addresses = $this->verify_model->get_address_count();

		parent::logger(min($this->total_addresses, $this->quota)." addresses to verify.", 2);

		//stop here if there are no addresses to verify
		if ($this->total_addresses < 1) {
			return;
		}
		
		//we'll be using a while loop to loop through batches
		//so we'll use this variable to remember how many addresses were in the previous chunk
		//and when it's less than 100, or whatever the batch size is, we'll know we've reached the last batch
		//since this is the first batch, and there is no previous batch, we need to set it to 100, or whatever the batch size is.
		$this->address_count = $this->chunk_size;
		
		//we'll use this variable to keep track of how many addresses we've processes
		$this->offset = 0;
		
		//loop through batches, as long as:
		//we haven't just processes the last batch; and
		//we haven't reached the maximum number of addresses to verify
		while ($this->address_count >= $this->chunk_size && $this->offset < $this->quota) {
			//process the batch
			$this->verify_batch();
		}
	}

	/**
	 * Process a batch of addresses.
	 */
	function verify_batch() {
		//start a timer so we know how long the batch takes
		$this->batch_timer = microtime(true);

		//get addresses in this batch
		$unclean_addresses = $this->verify_model->get_unclean_addresses();

		parent::logger("\nVerifying batch {$this->offset} - ".($this->offset + count($unclean_addresses)).".", 3);
	
		//loop through addresses
		foreach ($unclean_addresses as $address) {
			//process the address
			$this->verify_address($address);
		}

		//how long did it take to process this batch?
		$batch_time_elapsed = round(microtime(true) - $this->batch_timer, 3);

		//how long per address, on average?
		$time_per_address = round($batch_time_elapsed / count($unclean_addresses), 3);

		//how long to process all the remaining batches?
		$time_predicted = round($time_per_address * (min($this->total_addresses, $this->quota) - $this->offset - count($unclean_addresses)), 3);

		//success rate?
		$verification_success = round($this->verification_successful / ($this->verification_successful + $this->verification_failed) * 100, 2);

		parent::logger("Batch {$this->offset} - ".($this->offset + count($unclean_addresses))." verified; {$batch_time_elapsed}s; {$time_per_address}s per address; {$time_predicted}s remaining; {$verification_success}% success.", 2);
	
		//how many addresses were in this batch?
		//so the while loop will know when we've reached the last batch
		$this->address_count = count($unclean_addresses);

		//update the number of addresses we've processed
		$this->offset += $this->chunk_size;
	}

	/**
	 * Process an individual address.
	 */
	function verify_address($address) {
		parent::logger("Verifying address:\n".print_r($address, 1), 4);

		//verify the address
		$check = $this->qas_query($address);

		//if we didn't get anything back from the verifier
		if (empty($check)) {
			parent::logger("QAS has returned NULL.", 1);
/* 			parent::logger("Address: {$address['sys_address_id']}; {$address['address']}", 1); */

			//tell the database that this address can't be verified
			$this->verify_model->cant_verify($address, NULL);

			$this->verification_failed ++;

			//go on to the next address
			return;
		}

		//get the check code and everything else from the data the verifier returned
		list($check_lines, $check_code) = $this->get_qas_check_code($check);

		//if the check code doesn't start with R, then verification failed
		//or if the verifier didn't return an address
		if (substr($check_code, 0, 1) != "R" || empty($check_lines)) {
			//log the appropriate message
			if (substr($check_code, 0, 1) != "R" ) {
				parent::logger("QAS cannot verify this address.", 3);
			} else if (empty($check_lines)) {
				parent::logger("QAS has returned no address.", 3);
			}

/* 			parent::logger("Address: {$address['sys_address_id']}; {$address['address']}", 3); */

			//tell the database that this address can't be verified
			$this->verify_model->cant_verify($address, $check_code);

			$this->verification_failed ++;

			//go on to the next address
			return;
		}

		//disect the address into street, suburb, state, etc. and put it into an array
		$address_verified = $this->disect_verified_address($check_lines);

		//save the verified address to the database
		$this->verify_model->can_verify($address, $check_code, $address_verified);

		parent::logger("Address verified.", 3);

		$this->verification_successful ++;
	}

	/**
	 * Run QAS and verify an address.
	 */
	function qas_query($address) {
		//put the address into the format that QAS will be expecting
		$check_address = $address['address'].", ".$address['suburb'].", ".$address['state'].", ".$address['postcode'];
	
		//run QAS
		$check = shell_exec($this->qas_path." ".escapeshellarg($check_address)."");
		
		//trim whitespace
		$check = trim($check);

		//return the check code and verified address
		return $check;
	}

	/**
	 * Separate the check code from the verified address.
	 */
	function get_qas_check_code($check) {
		//split the lines that the verifier retuned into an array
		$check_lines = explode("\n", $check);

		//slice of the first row - the check code
		$check_code = array_shift($check_lines);

		//return the check code and the verified address
		return array($check_lines, $check_code);
	}

	/**
	 * Disect the address into street, suburb, state, etc. into a usable array.
	 */
	function disect_verified_address($check_lines) {
		//anything on or after the 4th line is unused data - extra address data like c/o
		$address_notused = array_slice($check_lines, 4);

		//the 3rd line contains the suburb, state and postcode, separated by double spaces
		$check_suburb_state_postcode = explode("  ", $check_lines[2]);

		//the street address is spread accross the first 2 lines
		//add the street address to the address field in the array
		$address_verified['address'] = $check_lines[0]."\n".$check_lines[1];

		//add unused data to the address field, before the street address
		if (!empty($address_notused)) $address_verified['address'] = implode("\n", $address_notused)."\n".$address_verified['address'];

		//add suburb, state, etc. fields to the array
		$address_verified['suburb'] = $check_suburb_state_postcode[0];
		$address_verified['state'] = $check_suburb_state_postcode[1];
		$address_verified['postcode'] = $check_suburb_state_postcode[2];
		$address_verified['country'] = "AUSTRALIA";

		//return the address array
		return $address_verified;
	}
}

?>