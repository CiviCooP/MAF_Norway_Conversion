<?php

error_reporting(E_ALL);

require_once('check_ip.php');

require_once('config.php');
require_once('civi/correctAddress.php');
require_once('civi/correctRecurringContribution.php');

class correction {

	protected $api;
	
	protected $config;
	
	protected $pdo;
	
	protected $civi_pdo;
	
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
	}
	
	public function addresses() {
		$offset = 0;
		$debug = false;
		if (isset($_GET['offset'])) {
			$offset = $_GET['offset'];
		}

		$limit = 50;
		$i = new correctAddress($this->pdo, $this->civi_pdo, $this->api, $this->config, $offset, $limit);
		if ($i->getCount() == $limit) {
			echo "correction.php?addresses=1&offset=".($offset+$limit);
			
			echo "<script>
				setTimeout('herladen()', 1000);
   
				function herladen() {
					window.location = 'correction.php?addresses=1&offset=".($offset+$limit)."';
				}
			</script>";
		} 
	}
	
	public function recurring_contribution() {
		$offset = 0;
		$debug = false;
		if (isset($_GET['offset'])) {
			$offset = $_GET['offset'];
		}

		$limit = 300;
		$i = new correctRecurringContribution($this->pdo, $this->civi_pdo, $this->api, $this->config, $offset, $limit);
		if ($i->getCount() == $limit) {
			echo "correction.php?recurring_contribution=1&offset=".($offset+$limit);
			
			echo "<script>
				setTimeout('herladen()', 1000);
   
				function herladen() {
					window.location = 'correction.php?recurring_contribution=1&offset=".($offset+$limit)."';
				}
			</script>";
		} 
	}
	
}

$correction = new correction(new Conversion_Config());
if (isset($_GET['addresses']) && $_GET['addresses'] == 1) {
	$correction->addresses();
} elseif (isset($_GET['recurring_contribution']) && $_GET['recurring_contribution'] == 1) {
	$correction->recurring_contribution();
}

?>