<?php

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

?>