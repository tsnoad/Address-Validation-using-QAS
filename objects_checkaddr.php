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
 * Verify some addresses!
 * Run as a method from one of the models. Does all the work.
 */
class verifyProcess extends systemController {
	/**
	 * Set up variables that will be used later.
	 */
	function __construct() {
		//path to the compiled C script that does the verifing
		$this->qas_path = "/home/TSnoad/checkaddr/qabwvcd/qas_startup";

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
	
/*
		//loop through addresses
		foreach ($unclean_addresses as $address) {
			//process the address
			$this->verify_address($address);
		}
*/

		foreach (array_chunk($unclean_addresses, 20) as $unclean_addresses_chunk) {
			unset($pids);

			foreach ($unclean_addresses_chunk as $address) {
				$pid = pcntl_fork();

				if ($pid == -1) {
					die('could not fork');
				} else if ($pid) {
					// we are the parent
					$pids[] = $pid;
				} else {
					// we are the child

					$this->verification_failed = 0;
					$this->verification_successful = 0;

					$this->verify_address($address);

					if ($this->verification_successful > 0) {
						exit(0);
					} else {
						exit(1);
					}
				}
			}

			foreach ($pids as $pid) {
				pcntl_waitpid($pid, $status);

				if (pcntl_wexitstatus($status) === 0) {
					$this->verification_successful ++;
				} else {
					$this->verification_failed ++;
				}
			}
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
		//but first we have to be in the same directory as qas_startup
		//but we have to get php's cwd so we can come back when we're done
		$oldcwd = getcwd();
		chdir(dirname($this->qas_path));
		$check = shell_exec($this->qas_path." ".escapeshellarg($check_address)."");
		chdir($oldcwd);
		
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