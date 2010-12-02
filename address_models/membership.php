<?php

/**
 * Model for membership database.
 * Used to verify addresses that have never been verified previously.
 */
class membershipAddresses extends systemController {
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

?>