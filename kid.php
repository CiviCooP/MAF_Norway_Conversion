<?php

error_reporting(E_ALL);

require_once('check_ip.php');

require_once('config.php');
require_once('civi/tempCustomFields.php');

class kid {

	protected $api;
	
	protected $config;
	
	protected $pdo;
	
	protected $civi_pdo;
	
	protected $fields;
	
	public function __construct(Conversion_Config $config) {
		set_time_limit(40*60); 
		ini_set('memory_limit', '256M');
		
		$this->config = $config;
		
		$this->pdo = new PDO('mysql:host='.$this->config->db_hostname.';dbname='.$this->config->db_name.";charset=utf-8", $this->config->db_username, $this->config->db_password, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
		$this->pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, true);
		$this->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true); 
		$this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_LAZY);
		
		$this->civi_pdo = new PDO('mysql:host='.$this->config->civi_db_hostname.';dbname='.$this->config->civi_db_name.";charset=utf-8", $this->config->civi_db_username, $this->config->civi_db_password, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));
		$this->civi_pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
		$this->civi_pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, true);
		$this->civi_pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true); 
		$this->civi_pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_LAZY);
		
		require_once($this->config->civi_path."api/class.api.php");
		$this->api = new civicrm_api3 (array('conf_path'=> $this->config->drupal_path));	
		
		$this->fields = new tempCustomFields($this->pdo, $this->api, $this->config);
	}
	
	/**
	 * During the migration the KID numbers got mixed up, they consist of AKTIVITET ID + NAVN ID + CHECKSUM
	 * and the first two (AKTIVITET and NAVN should be swapped around
	 * So that is what this function is doing
	 */
	public function correct() {
		$offset = 0;
		$limit = 1000;
		$debug = false;
		if (isset($_GET['offset'])) {
			$offset = $_GET['offset'];
		}
		
		$group = $this->fields->getCustomGroup('maf_norway_aksjon_import');
		$field = $this->fields->getCustomFieldFull('aksjon_kid15');
		$field_backup = $this->fields->getCustomFieldFull('aksjon_kid15_correction');
		$table = $group->table_name;
		$f = $field->column_name;
		$f2 = $field_backup->column_name;
		
		$sql = "SELECT `".$f."`, `id` FROM `".$table."` LIMIT ".$offset.", ".$limit;
		$stmnt = $this->civi_pdo->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
		$stmnt->execute();
		$i=0;
		while ($row = $stmnt->fetch()) {
			$aktivitet = (int) substr($row[$f], 0, 6);
			$navn = (int) substr($row[$f], 6, 8);
			
			$kid15 = $this->kid_number_generate_15digit($navn, $aktivitet);
			
			$update = "UPDATE `".$table."` SET `".$f2."` = '".$kid15."' WHERE `id` = '".$row['id']."';";
			$this->civi_pdo->exec($update);
			
			$i++;
		}
		
		if ($i > 0) {
			echo "kid.php?kid=1&offset=".($offset+$limit);
			
			echo "<script>
				setTimeout('herladen()', 1000);
   
				function herladen() {
					window.location = 'kid.php?kid=1&offset=".($offset+$limit)."';
				}
			</script>";
		}
	}
	
	/*
	* Generate a 15 digit kid number
	*/
	protected function kid_number_generate_15digit($navn_id, $aktiviteit_id) {
		$kid_number = str_pad($navn_id, 6, '0', STR_PAD_LEFT);
		$kid_number = $kid_number . str_pad($aktiviteit_id, 8, '0', STR_PAD_LEFT);
		return $kid_number . $this->kid_number_generate_checksum_digit($kid_number);
	}
	
	/*
	* Generate checksum digit using the Luhn algorithm
	*/
	protected function kid_number_generate_checksum_digit($number) {
		$sum    = 0;
		$parity = strlen((string)$number) % 2;
		for ($i = strlen((string)$number)-1; $i >= 0; $i--) {
			$digit = $number[$i];
			if (!$parity == ($i % 2)) {
				$digit <<= 1;
			}
			$digit = ($digit > 9) ? ($digit - 9) : $digit;
			$sum += $digit;
		}
		return $sum % 10;
	}
	
}

$kid = new Kid(new Conversion_Config());
if (isset($_GET['kid']) && $_GET['kid'] == 1) {
	$kid->correct();
}

?>