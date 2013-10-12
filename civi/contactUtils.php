<?php 

class contactUtils {

	protected $config;
	
	protected $pdo;
	
	protected $civi_pdo;
	
	protected $api;

	public function __construct(PDO $pdo, PDO $civi_pdo, $api, $config) {
		$this->config = $config;
		$this->pdo = $pdo;
		$this->civi_pdo = $civi_pdo;
		$this->api = $api;
	
	}
	
	public function getContactIdFromNavn($navn_id) {
		$contactId = false;
		if (!$this->api->Contact->getsingle(array('contact_id' => $navn_id))) {
			$contactId = $this->checkDuplicate($navn_id);
		} else {
			$contactId = $this->api->id;
		}
		
		$contactId = $this->checkForHouseHoldId($contactId);
		
		return $contactId;
	}
	
	
	protected function checkForHouseHoldId($contactId) {
		if ($contactId === false) {
			return false;
		}
		
		//check for relationship
		$params['relationship_type_id'] = 7; //head of household
		$params['contact_id_a'] = $contactId;
		if ($this->api->Relationship->getsingle($params)) {
			return $this->api->contact_id_b;
		}
		return $contactId;
	}
	
	protected function checkDuplicate($navn_id) {
		$sql = "SELECT * FROM `PMF_MAF_NAVN_txt` `p` WHERE `L_NAVN_ID` = '".$navn_id."'";
		$stmnt = $this->pdo->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
		$stmnt->execute();
		if ($stmnt->rowCount()) {
			$row = $stmnt->fetch();
			if ($this->api->Contact->getsingle(array('contact_id' => $row['L_DUPLIKATVINNER_ID']))) {
				return $this->api->id;
			}
		}
		return false;
	}
	
	public function getContactType($name, $label, $parent_id) {
		if ($this->api->ContactType->getsingle(array('name' => $name))) {
			return $this->api->result->name;
		}
		$params = array(
			'name' => $name,
			'label' => $label,
			'parent_id' => $parent_id,
			'is_active' => '1',
		);
		
		if ($this->api->ContactType->create($params)) {
			return $this->api->values[0]->name;
		}
		return false;
	}
	
	public function getReason($reason) {
		switch($reason) {
			case 'A':
				return 'Ukjent grunn'; //unknown reasons
				break;
			case 'U':
				return 'Adresse ukjent'; //unknown address
				break;
			case 'V':
				return 'Død'; //dead
				break;
			case 'C1':
				return 'Dublett'; //double
				break;
			case 'EØ':
				return 'Etter eget ønske'; //contact requested to stop
				break;
			case 'PM':
				return 'Potensielt medlem'; //potential member
				break;
		}
		return false;
	}
	
	public function formatDate($date) {
		$d = new DateTime($date);
		return $d->format('Y-m-d');
	}
	
	public function convertStrToFloat($str) {
		$s = str_replace(".", "", $str);
		$parts = explode(",", $s);
		$int = (int) $parts[0];
		$f = 0;
		if (isset($parts[1])) {
			$f = (float) ('.'.$parts[1]);
		}
		if ($int < 0) {
			$float = (float) $int - $f;
		} else {
			$float = (float) $int + $f;
		}
		$float = number_format($float, 2, ',', '');
		return $float;
	}
	
}