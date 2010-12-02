#!/usr/bin/php
<?php

require_once("objects_checkaddr.php");

//get the models
require_once("address_models/authentication.php");
require_once("address_models/membership.php");

//run verification on addresses in the authentication database
//get the class. __construct() will start the verification process
$verify_auth = New authenticationAddresses;

//run verification on addresses, in the authentication database, that have changed and require reverification
$verify_auth_mod = New authenticationAddressesModified;

//run verification on addresses in the membership database
$verify_memb = New membershipAddresses;

?>