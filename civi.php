<?php

error_reporting(E_ALL);

require_once('check_ip.php');

require_once('config.php');
require_once('civi/importContacts.php');
require_once('civi/importPledges.php');
require_once('civi/importPledgePayments.php');
require_once('civi/importGiver.php');
require_once('civi/importAksjon.php');
require_once('civi/importAktivitet.php');
require_once('civi/importActivityTarget.php');

class Civi {

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
		
		$this->check_requirements();
		
	}
	
	public function clearImport() {
		//$this->civi_pdo->prepare("SET FOREIGN_KEY_CHECKS=0;", array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY))->execute();
		$this->civi_pdo->exec("DELETE FROM `civicrm_contact` WHERE `id` > 12;");
		$this->civi_pdo->exec("DELETE FROM `civicrm_relationship` WHERE `contact_id_a` > 12;");
		$this->civi_pdo->exec("DELETE FROM `civicrm_relationship` WHERE `contact_id_b` > 12;");
		$this->civi_pdo->exec("DELETE FROM `civicrm_address` WHERE `contact_id` > 12;");
		$this->civi_pdo->exec("DELETE FROM `civicrm_phone` WHERE `contact_id` > 12;");
		$this->civi_pdo->exec("DELETE FROM `civicrm_website` WHERE `contact_id` > 12;");
		
		$this->civi_pdo->exec("DELETE FROM `civicrm_email` WHERE `contact_id` > 12;");
		$this->civi_pdo->exec("DELETE FROM `civicrm_entity_tag` WHERE `entity_id` > 12 AND `entity_table` = 'civicrm_contact';");
		$this->civi_pdo->exec("DELETE FROM `civicrm_group_contact` WHERE `contact_id` > 12;");
		$this->civi_pdo->exec("DELETE FROM `civicrm_pledge_payment`;");
		$this->civi_pdo->exec("DELETE FROM `civicrm_pledge`;");
		$this->civi_pdo->exec("DELETE FROM `civicrm_contribution`;");
		
		$this->civi_pdo->exec("DELETE FROM `civicrm_contribution_recur`;");
		$this->civi_pdo->exec("DELETE FROM `civicrm_contribution_recur_offline`;");
		
		$this->civi_pdo->exec("DELETE FROM `civicrm_activity_target`;");
		$this->civi_pdo->exec("DELETE FROM `civicrm_activity`;");
		
		$this->civi_pdo->exec("DELETE FROM `civicrm_note`;");
		
		$this->civi_pdo->exec("DROP TABLE IF EXISTS `civicrm_contribution_recur_import`");
		
		//$this->civi_pdo->prepare("SET FOREIGN_KEY_CHECKS=1;", array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY))->execute();
		
		tempCustomFields::deleteFields($this->api);
		
		//$this->civi_pdo->prepare("", array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY))->execute();
		//$this->civi_pdo->prepare("", array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY))->execute();
		
		
		
		$sql = "SET FOREIGN_KEY_CHECKS=0;
		
				DELETE FROM `civicrm_contact` WHERE `id` > 12;
				DELETE FROM `civicrm_relationship` WHERE `contact_id_a` > 12;
				DELETE FROM `civicrm_relationship` WHERE `contact_id_b` > 12;

				DELETE FROM `civicrm_phone` WHERE `contact_id` > 12;
				DELETE FROM `civicrm_website` WHERE `contact_id` > 12;
				DELETE FROM `civicrm_value_maf_norway_import_10` WHERE `entity_id` > 12;
				DELETE FROM `civicrm_value_maf_norway_individual_8` WHERE `entity_id` > 12;
				DELETE FROM `civicrm_email` WHERE `contact_id` > 12;
				DELETE FROM `civicrm_entity_tag` WHERE `entity_id` > 12 AND `entity_table` = 'civicrm_contact';
				
				DELETE FROM `civicrm_pledge_payment`;
				DELETE FROM `civicrm_pledge`;
				
				SET FOREIGN_KEY_CHECKS=1;
				";
	
		//$this->civi_pdo->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY))->execute();
	}
	
	protected function check_requirements() {
		$this->check_extension('org.civicoop.general.api.country');
		$this->check_extension('org.civicoop.general.api.financialtype');
		//$this->check_extension('org.civicoop.no.maf.custom');
	}
	
	protected function check_extension($key) {
		$found = false;
		$installed = false;
		if ($this->api->Extension->get()) {
			foreach($this->api->values as $ext) {
				if ($ext->key == $key) {
					$found = true;
					if ($ext->status == "installed") {
						$installed = true;
					}
					break;
				}
			}
		}
		
		if (!$found) {
			Throw new Exception("Unknown extension ".$key);
		} elseif (!$installed)  {
			Throw new Exception("Extension ".$key. " is not installed");
		}
	}

	public function import() {
		$offset = 0;
		$debug = false;
		if (isset($_GET['offset'])) {
			$offset = $_GET['offset'];
		}
		if (isset($_GET['debug']) && $_GET['debug'] == '1') {
			$debug = true;
		}
		$limit = 250;
		$i = new importContacts($this->pdo, $this->civi_pdo, $this->api, $this->config, $offset, $limit, $debug);
		if ($i->getCount() == $limit) {
			echo "/sites/all/modules/conversion/civi.php?contact=1&offset=".($offset+$limit);
			
			echo "<script>
				setTimeout('herladen()', 1000);
   
				function herladen() {
					window.location = '/sites/all/modules/conversion/civi.php?contact=1&offset=".($offset+$limit)."';
				}
			</script>";
		} elseif (!$debug) {
			echo "/sites/all/modules/conversion/civi.php?givers=1";
			
			echo "<script>
				setTimeout('herladen()', 1000);
   
				function herladen() {
					window.location = '/sites/all/modules/conversion/civi.php?givers=1';
				}
			</script>";
		}
	}
	
	public function importPledges() {		
		$offset = 0;
		if (isset($_GET['offset'])) {
			$offset = $_GET['offset'];
		}
		$limit = 300;
		$i = new importPledges($this->pdo, $this->civi_pdo, $this->api, $this->config, $offset, $limit);
		if ($i->getCount() == $limit) {
			echo "/sites/all/modules/conversion/civi.php?pledges=1&offset=".($offset+$limit);
			
			echo "<script>
				setTimeout('herladen()', 300);
   
				function herladen() {
					window.location = '/sites/all/modules/conversion/civi.php?pledges=1&offset=".($offset+$limit)."';
				}
			</script>";
		} else {
			echo "/sites/all/modules/conversion/civi.php?payments=1&offset=";
			
			echo "<script>
				setTimeout('herladen()', 1000);
   
				function herladen() {
					window.location = '/sites/all/modules/conversion/civi.php?payments=1';
				}
			</script>";
		}
	}
	
	public function importPayments() {		
		$offset = 0;
		$debug = false;
		if (isset($_GET['offset'])) {
			$offset = $_GET['offset'];
		}
		if (isset($_GET['debug']) && $_GET['debug'] == '1') {
			$debug = true;
		}
		$limit = 500;
		$i = new importPledgePayments($this->pdo, $this->civi_pdo, $this->api, $this->config, $offset, $limit, $debug);
		if ($i->getCount() == $limit) {
			echo "/sites/all/modules/conversion/civi.php?payments=1&offset=".($offset+$limit);
			
			echo "<script>
				setTimeout('herladen()', 300);
   
				function herladen() {
					window.location = '/sites/all/modules/conversion/civi.php?payments=1&offset=".($offset+$limit)."';
				}
			</script>";
		} /*else {
			echo "/sites/all/modules/conversion/civi.php?aktivitet=1&offset=".($offset+$limit);
			
			echo "<script>
				setTimeout('herladen()', 1000);
   
				function herladen() {
					window.location = '/sites/all/modules/conversion/civi.php?aktivitet=1&offset=".($offset+$limit)."';
				}
			</script>";
		}*/
	}
	
	public function importGivers() {		
		$offset = 0;
		$debug = false;
		if (isset($_GET['offset'])) {
			$offset = $_GET['offset'];
		}
		if (isset($_GET['debug']) && $_GET['debug'] == '1') {
			$debug = true;
		}
		$limit = 500;
		$i = new importGiver($this->pdo, $this->civi_pdo, $this->api, $this->config, $offset, $limit, $debug);
		if ($i->getCount() == $limit) {
			echo "/sites/all/modules/conversion/civi.php?givers=1&offset=".($offset+$limit);
			
			echo "<script>
				setTimeout('herladen()', 1000);
   
				function herladen() {
					window.location = '/sites/all/modules/conversion/civi.php?givers=1&offset=".($offset+$limit)."';
				}
			</script>";
		} elseif (!$debug) {
			echo "/sites/all/modules/conversion/civi.php?pledges=1";
			
			echo "<script>
				setTimeout('herladen()', 1000);
   
				function herladen() {
					window.location = '/sites/all/modules/conversion/civi.php?pledges=1';
				}
			</script>";
		}
	}
	
	public function importAksjon() {		
		$offset = 0;
		if (isset($_GET['offset'])) {
			$offset = $_GET['offset'];
		}
		$limit = 2;
		$i = new importAksjon($this->pdo, $this->civi_pdo, $this->api, $this->config, $offset, $limit);
		if ($i->getCount() == $limit) {
			echo "/sites/all/modules/conversion/civi.php?aksjon=1&offset=".($offset+$limit);
			
			echo "<script>
				setTimeout('herladen()', 1000);
   
				function herladen() {
					window.location = '/sites/all/modules/conversion/civi.php?aksjon=1&offset=".($offset+$limit)."';
				}
			</script>";
		} else {
			echo "/sites/all/modules/conversion/civi.php?activity_target=1";
			
			echo "<script>
				setTimeout('herladen()', 1000);
   
				function herladen() {
					window.location = '/sites/all/modules/conversion/civi.php?activity_target=1';
				}
			</script>";
		}
	}
	
	public function importAktivitet() {		
		$offset = 0;
		if (isset($_GET['offset'])) {
			$offset = $_GET['offset'];
		}
		$limit = 1000;
		$i = new importAktivitet($this->pdo, $this->civi_pdo, $this->api, $this->config, $offset, $limit);
		if ($i->getCount() == $limit) {
			echo "/sites/all/modules/conversion/civi.php?aktivitet=1&offset=".($offset+$limit);
			
			echo "<script>
				setTimeout('herladen()', 1000);
   
				function herladen() {
					window.location = '/sites/all/modules/conversion/civi.php?aktivitet=1&offset=".($offset+$limit)."';
				}
			</script>";
		} /*else {
			echo "/sites/all/modules/conversion/civi.php?pledges=1";
			
			echo "<script>
				setTimeout('herladen()', 1000);
   
				function herladen() {
					window.location = '/sites/all/modules/conversion/civi.php?pledges=1';
				}
			</script>";
		}*/
	}
	
	public function importActivityTarget() {		
		$offset = 0;
		if (isset($_GET['offset'])) {
			$offset = $_GET['offset'];
		}
		$limit = 500;
		$i = new importActivityTarget($this->pdo, $this->civi_pdo, $this->api, $this->config, $offset, $limit);
		if ($i->getCount() == $limit) {
			echo "/sites/all/modules/conversion/civi.php?activity_target=1&offset=".($offset+$limit);
			
			echo "<script>
				setTimeout('herladen()', 300);
   
				function herladen() {
					window.location = '/sites/all/modules/conversion/civi.php?activity_target=1&offset=".($offset+$limit)."';
				}
			</script>";
		} else {
			echo "/sites/all/modules/conversion/civi.php?pledges=1&offset=";
			
			echo "<script>
				setTimeout('herladen()', 1000);
   
				function herladen() {
					window.location = '/sites/all/modules/conversion/civi.php?pledges=1';
				}
			</script>";
		}
	}
}

$civi = new Civi(new Conversion_Config());
if (isset($_GET['pledges']) && $_GET['pledges'] == 1) {
	$civi->importPledges();
} elseif (isset($_GET['payments']) && $_GET['payments'] == 1) {
	$civi->importPayments();
} elseif (isset($_GET['givers']) && $_GET['givers'] == 1) {
	$civi->importGivers();
} elseif (isset($_GET['aktivitet']) && $_GET['aktivitet'] == 1) {
	$civi->importAktivitet();
} elseif (isset($_GET['aksjon']) && $_GET['aksjon'] == 1) {
	$civi->importAksjon();
} elseif (isset($_GET['activity_target']) && $_GET['activity_target'] == 1) {
	$civi->importActivityTarget();
} elseif (isset($_GET['clear']) && $_GET['clear'] == '0123456789') {
	$civi->clearImport();
} elseif (isset($_GET['contact']) && $_GET['contact'] == 1) {
	$civi->import();
}