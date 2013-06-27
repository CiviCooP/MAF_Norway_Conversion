<?php

require_once('contactUtils.php');

class importPledgePayments {
	
	protected $config;
	
	protected $pdo;
	
	protected $civi_pdo;
	
	protected $api;
	
	protected $count = 0;
	
	protected $util;
	
	protected $payment_methods = array(
		'0' => 'OCR-giro',
		'5' => 'AvtaleGiro',
		'6' => 'Bank-overføring',
		'8' => 'Kontant',
		'9' => 'Betalingskort',
		'10' => 'Gavetelefon Phonebanking',
		'11' => 'SMS',
		'12' => 'PayPal',
	);
	
	protected $payment_options_group = 10;
	
	public function __construct(PDO $pdo, PDO $civi_pdo, $api, $config, $offset, $limit) {
		$this->config = $config;
		$this->pdo = $pdo;
		$this->civi_pdo = $civi_pdo;
		$this->api = $api;
		
		$this->util = new contactUtils($pdo, $civi_pdo, $api, $config);
		
		$this->count = $this->import($offset, $limit);
	}
	
	public function getCount() {
		return $this->count;
	}
	
	protected function findPayments($offset, $limit) {
		$sql = "SELECT `p`.*, `i`.`I_BETALINGSMAATE`, `i`.`L_AKSJON_ID`, `i`.`D_DATO`, `i`.`L_AKTIVITET_ID` AS `I_L_AKTIVITET_ID` FROM `PMF_MAF_POSTERING_txt` `p` LEFT JOIN `PMF_MAF_INNBETALING_txt` AS `i` ON `p`.`L_INNBETALING_ID` = `i`.`L_INNBETALING_ID` WHERE YEAR(`i`.`D_DATO`) = 2013 LIMIT ".$offset .", ". $limit;
		$stmnt = $this->pdo->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
		$stmnt->execute();
		return $stmnt;
	}
	
	protected function findProduktType($id) {
		$sql = "SELECT * FROM `PMF_MAF_PRODUKTTYPE_txt` `p` WHERE `A_PRODUKTTYPE_ID` = '".$id."'";
		$stmnt = $this->pdo->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
		$stmnt->execute();
		return $stmnt->fetch();
	}
	
	protected function findPaymentMethod($id) {
		$return = false;
		if (isset($this->payment_methods[$id])) {
			$params['name'] = $this->payment_methods[$id];
			$params['option_group_id'] = $this->payment_options_group;
			if ($this->api->OptionValue->getSingle($params)) {
				return $this->api->value;
			}
			$params['name'] = $this->payment_methods[$id];
			$params['option_group_id'] = $this->payment_options_group;
			if ($this->api->OptionValue->create($params)) {
				return $this->api->values[0]->value;
			}
		}
		return $return;
	}
	
	protected function import($offset, $limit) {
		$stmnt = $this->findPayments($offset, $limit);
		$i=0;
		while ($row = $stmnt->fetch()) {
			$this->importPledgePayment($row);
			$i ++;
		}
		return $i;
	}
	
	protected function importPledgePayment($row) {
		$doNotImport = false;
		$contactId = $this->util->getContactIdFromNavn($row['L_NAVN_ID']);
		if (!$contactId) {
			echo "<span style=\"color: red;\">Contact ".$row['L_NAVN_ID']." not found</span><br>";
			$doNotImport = true;			
		}
		
		if ($doNotImport) {
			return;
		}
	
		$params['contact_id'] = $contactId;
		$params['total_amount'] = (float) str_replace(",", ".", $row['M_BELOEP']);
		$params['receive_date'] = $row['D_DATO'];
		
		$this->determinPaymentMethod($params, $row);
		$financial_type_id = $params['financial_type_id'];
		
		if ($row['I_BETALINGSMAATE'] != '0') {
			$payment_id = $this->findPaymentMethod($row['I_BETALINGSMAATE']);
			if ($payment_id) {
				$params['contribution_payment_instrument_id'] = $payment_id;
			}
		}
		
		if ($this->api->CustomField->getsingle(array('name' => 'Aksjon_ID'))) {
			if ($row['L_AKSJON_ID']) {
				$params['custom_'.$this->api->id] = $row['L_AKSJON_ID'];
			}
		}
		
		if ($this->api->CustomField->getsingle(array('name' => 'Orgininal_contact_ID'))) {
			if ($row['L_NAVN_ID']) {
				$params['custom_'.$this->api->id] = $row['L_NAVN_ID'];
			}
		}
		
		if ($this->api->CustomField->getsingle(array('name' => 'Aktivitet_ID'))) {
			if ($row['I_L_AKTIVITET_ID']) {
				$params['custom_'.$this->api->id] = $row['I_L_AKTIVITET_ID'];
			}
		}
		
		if ($this->api->Contribution->Create($params)) {
			$contribution_id = $this->api->id;
			$status_id = $this->api->values[0]->contribution_status_id;
			echo "<span style=\"color: green;\">Created payment for contact ".$contactId.": ".$this->api->id."</span><br>";
			//check for pledge			
			$this->checkPledge($row, $contactId, $contribution_id, $status_id, $financial_type_id);
		}
	}
	
	protected function checkPledge($row, $contactId, $contribution_id, $status_id, $financial_type_id) {
		//check if contribution is a pledge
		if ($this->findPledge($row) == 0) {
			//this is not a pledge
			return;
		}
		
		$params['contact_id'] = $contactId;
		$params['financial_type_id'] = $financial_type_id;
		//$params['scheduled_amount'] = (float) str_replace(",", ".", $row['M_BELOEP']);
		$pledge = false;
		$pledges = array();
		if ($this->api->Pledge->get($params)) {
			foreach($this->api->values as $p) {
				$params2['scheduled_amount'] = (float) str_replace(",", ".", $row['M_BELOEP']);	
				$params2['pledge_id'] = $p->pledge_id;
				$api = clone $this->api;
				if ($api->PledgePayment->get($params2)) {
					foreach($api->values as $value) {
						if ($value->status_id == 6 || $value->status_id == 2) {
							$pledge = $value;
							break;
						}
					}
				}
			}
		}
		
		if ($pledge !== false) {
			unset($params);
			$params['id']  = $pledge->id;
			$params['status_id'] = $status_id;
			$params['contribution_id'] = $contribution_id;
			$params['pledge_payment_actual_amount'] = (float) str_replace(",", ".", $row['M_BELOEP']);
			$this->api->PledgePayment->Create($params);
			echo "<span style=\"color: green;\">Created pledge payment: ".$this->api->id."</span><br>";
		}
	}
	
	protected function findPledge($row) {
		$sql = "SELECT * FROM `PMF_MAF_AVTALE_txt` `p` WHERE `A_PRODUKTTYPE_ID` = '".$row['A_PRODUKTTYPE_ID']."' AND `L_NAVN_ID` = '".$row['L_NAVN_ID']."'";
		$stmnt = $this->pdo->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
		$stmnt->execute();
		return $stmnt->rowCount();
	}
	
	protected function determinPaymentMethod(&$params, $row) {
		$r = $this->findProduktType($row['A_PRODUKTTYPE_ID']);
		$type = $r['A_PRODUKTTYPENAVN'];
		$createNew = true;
		if ($this->api->FinancialType->get(array('name' => $type))) {
			if ($this->api->count > 1) {
				$params['financial_type_id'] = $this->api->values[0]->id;
				$createNew = false;
			} elseif ($this->api->count == 1) {
				$params['financial_type_id'] = $this->api->id;
				$createNew = false;
			}
		} 
		if ($createNew) {
			$create['name'] = $type;
			$create['is_active'] = '1';
			$create['is_reserved'] = '0';
			$create['is_deductible'] = '0';
			
			if ($this->api->FinancialType->Create($create)) {
				$params['financial_type_id'] = $this->api->id;
			}
		}
	}
}