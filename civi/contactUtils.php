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
		$sql = "SELECT * FROM `PMF_MAF_NAVN_txt` `p` WHERE `L_DUPLIKATVINNER_ID` = '".$navn_id."'";
		$stmnt = $this->pdo->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
		$stmnt->execute();
		if ($stmnt->rowCount()) {
			$row = $stmnt->fetch();
			if ($this->api->Contact->getsingle(array('contact_id' => $row['L_NAVN_ID']))) {
				return $this->api->id;
			}
		}
		return false;
	}
	
}