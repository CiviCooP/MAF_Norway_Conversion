<?php

require_once('contactUtils.php');
require_once('importTagsGroups.php');

class importPledges {
	
	protected $config;
	
	protected $pdo;
	
	protected $civi_pdo;
	
	protected $api;
	
	protected $count = 0;	
	
	protected $util;
	
	protected $tags;
	
	protected $fields;
	
	protected $tag_pt_1;
	protected $tag_pt_2;
	protected $tag_pt_3;
	
	protected $tag_recurring_id;
	
	public function __construct(PDO $pdo, PDO $civi_pdo, $api, $config, $offset, $limit) {
		$this->config = $config;
		$this->pdo = $pdo;
		$this->civi_pdo = $civi_pdo;
		$this->api = $api;
		
		$this->util = new contactUtils($pdo, $civi_pdo, $api, $config);
		$this->fields = new tempCustomFields($pdo, $api, $config);
		$this->tags = new importTagsGroups($pdo, $civi_pdo, $api, $config);
		
		$this->civi_pdo->exec("CREATE TABLE IF NOT EXISTS `civicrm_contribution_recur_import` (
			`recur_id` int(10) unsigned NOT NULL,
			`aksjon_id` int(10) unsigned NOT NULL,
			`navn_id` int(10) unsigned NOT NULL,
			`produktttpe` varchar(32) NOT NULL default '',
			PRIMARY KEY (`recur_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=latin1;
		");
		
		$this->tag_recurring_id = $this->tags->create('Tag', 'name', 'Has active recurring contribution', false);
		$this->tag_pt_3 = $this->tags->create('Tag', 'name', 'Recurring contribution: Printed giro', false);
		$this->tag_pt_2 = $this->tags->create('Tag', 'name', 'Recurring contribution: Avtale giro', false);
		$this->tag_pt_1 = $this->tags->create('Tag', 'name', 'Recurring contribution: Donor Managed', false);
		
		$this->count = $this->import($offset, $limit);
	}
	
	public function getCount() {
		return $this->count;
	}
	
	protected function loadAllRecords($offset, $limit) {
		$sql = "SELECT * FROM `PMF_MAF_AVTALE_txt` `n`  LIMIT ".$offset .", ". $limit;
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
	
	protected function import($offset, $limit) {
		$stmnt = $this->loadAllRecords($offset, $limit);
		$i=0;
		while ($row = $stmnt->fetch()) {
			$this->importPledge($row);
			$i ++;
		}
		return $i;
	}
	
	protected function importPledge($row) {
		
		$doNotImport = false;
		$contactId = $this->util->getContactIdFromNavn($row['L_NAVN_ID']);
		if (!$contactId) {
			echo "<span style=\"color: red;\">Contact ".$row['L_NAVN_ID']." not found</span><br>";
			$doNotImport = true;
		}
		
		if (!strlen($row['M_BELOEP']) || $row['M_BELOEP'] == '0') {
			echo "<span style=\"color: orange;\">M_BELOEP: ".$row['M_BELOEP']."</span><br />";
			$doNotImport = true;
		}
		
		$payment_type = false;
		switch($row['I_BETALINGSMAATE']) {
			case '0': //Bank - OCR (imported through OCR-file from the bank, with KID-numbers) = printed giro
				$payment_type = 3; //printed giro
				break;
			case '5': //Bank - AvtaleGiro (also imported through OCR-file from the bank, with KID-numbers) = Avtala Giro
				$payment_type = 2; //Avtale giro
				break;
			case '6': //Bank - Overføring (manually registred payments from the bank) = Donor Managed
				$payment_type = 1; //Donor Managed
				break;
		}
		
		if (!$payment_type) {
			$doNotImport = false;
		}
		
		if ($doNotImport) {
			return false;
		}
		
		if ($row['D_STOPP_FRA'] !== null) {
			$slutt = new DateTime($row['D_STOPP_FRA']);
			if ($slutt->format('Y') < 2013) {
				if ($row['A_STOPPAARSAK'] != 'PM') {
					//do not import because member is an old donor. But tag the contact insetad
					$tag_id = $this->tags->create('Tag', 'name', 'Has been donor in the past', false);
					if ($tag_id !== false) {
						$this->tags->add('EntityTag', 'tag_id', $tag_id, $contactId);
					}
				}
				echo "<span style=\"color: orange;\">D_SLUTTDATO: ".$slutt->format('d-m-Y')."</span><br />";
				return false;
				
			}
		}
		
		if ($row['A_PRODUKTTYPE_ID'] == 'ME') {
			// do not import because this is a member. But add a tag to the contact saying that this is a member.
			$tag_id = $this->tags->create('Tag', 'name', 'Membership', false);
			if ($tag_id !== false) {
				$this->tags->add('EntityTag', 'tag_id', $tag_id, $contactId);
			}
			return false;
		}
	
		$params['contact_id'] = $contactId;
		$params['create_date'] = $row['D_REGDATO'];
		
		//paid to be in periods
		$this->determinePeriods($params, $row);
		
		//determine payment method
		$this->determinPaymentMethod($params, $row);
		
		if ($row['D_SLUTTDATO'] !== null) {
			$slutt = new DateTime($row['D_SLUTTDATO']);
			$params['end_date'] = $slutt->format('Y-m-d');
		} else {
			if ($this->tag_recurring_id !== false) {
				$this->tags->add('EntityTag', 'tag_id', $this->tag_recurring_id, $contactId);
			}
			switch($payment_type) {
				case 1:
					if ($this->tag_pt_1 !== false) {
						$this->tags->add('EntityTag', 'tag_id', $this->tag_pt_1, $contactId);
					}
					break;
				case 2:
					if ($this->tag_pt_2 !== false) {
						$this->tags->add('EntityTag', 'tag_id', $this->tag_pt_2, $contactId);
					}
					break;
				case 3:
					if ($this->tag_pt_3 !== false) {
						$this->tags->add('EntityTag', 'tag_id', $this->tag_pt_3, $contactId);
					}
					break;
			}
		}
		
		$params['custom_'.$this->fields->getCustomField('contributionrecur_aksjon_id')] = $row['L_AKSJON_ID'];
		
		if ($this->api->ContributionRecur->Create($params)) {
			$recur_id = $this->api->id;
			$activity_id = $this->getActivity($row);
			if (!$activity_id) {
				$activity_id = 0;
			}
			
			$this->civi_pdo->exec("INSERT INTO `civicrm_contribution_recur_offline` (`recur_id`, `maximum_amount`, `payment_type_id`, `activity_id`) VALUES ('".$recur_id."', '".$params['amount']."', '".$payment_type."', '".$activity_id."');");
			$this->civi_pdo->exec("INSERT INTO `civicrm_contribution_recur_import` (`recur_id`, `aksjon_id`, `navn_id`, `produktttpe`) VALUES ('".$recur_id."', '".$row['L_AKSJON_ID']."', '".$row['L_NAVN_ID']."', '".$row['A_PRODUKTTYPE_ID']."');");
		
			echo "<span style=\"color: green;\">Created pledge for contact ".$contactId.": ".$recur_id."</span><br>";
			return true;
		}
		return false;
	}
	
	protected function getActivity($row) {
		$activity = false;
		
		
		$group = $this->fields->getCustomGroup('maf_norway_aksjon_import');
		if (!$group) {
			return false;
		}
		$field = $this->fields->getCustomFieldFull('aksjon_id');
		if (!$field) {
			return false;
		}
		
		$sql = "SELECT `entity_id` FROM `".$group->table_name."` WHERE `".$field->column_name."` = '".$row['L_AKSJON_ID']."';";
		$stmnt = $this->civi_pdo->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
		$stmnt->execute();
		if ($r = $stmnt->fetch()) {
			return $r['entity_id'];
		}
		
		return false;
	}
	
	protected function determinePeriods(&$params, $row) {
		$periods = array();
		
		//I_GIRORYTME = to be paid in X months
		//M_BELOEP = total payment
		
		$custom = false; //custom period
		
		if (strlen($row['I_MAANEDUT01'])) {
			$periods[$row['I_MAANEDUT01']] = (float) str_replace(",", ".",$row['M_BELOEP01']);
		}
		if (strlen($row['I_MAANEDUT02'])) {
			$periods[$row['I_MAANEDUT02']] = (float) str_replace(",", ".",$row['M_BELOEP02']);
		}
		if (strlen($row['I_MAANEDUT03'])) {
			$periods[$row['I_MAANEDUT03']] = (float) str_replace(",", ".",$row['M_BELOEP03']);
		}
		if (strlen($row['I_MAANEDUT04'])) {
			$periods[$row['I_MAANEDUT04']] = (float) str_replace(",", ".",$row['M_BELOEP04']);
		}
		if (strlen($row['I_MAANEDUT05'])) {
			$periods[$row['I_MAANEDUT05']] = (float) str_replace(",", ".",$row['M_BELOEP05']);
		}
		if (strlen($row['I_MAANEDUT06'])) {
			$periods[$row['I_MAANEDUT06']] = (float) str_replace(",", ".",$row['M_BELOEP06']);
		}
		if (strlen($row['I_MAANEDUT07'])) {
			$periods[$row['I_MAANEDUT07']] = (float) str_replace(",", ".",$row['M_BELOEP07']);
		}
		if (strlen($row['I_MAANEDUT08'])) {
			$periods[$row['I_MAANEDUT08']] = (float) str_replace(",", ".",$row['M_BELOEP08']);
		}
		if (strlen($row['I_MAANEDUT09'])) {
			$periods[$row['I_MAANEDUT09']] = (float) str_replace(",", ".",$row['M_BELOEP09']);
		}
		if (strlen($row['I_MAANEDUT10'])) {
			$periods[$row['I_MAANEDUT10']] = (float) str_replace(",", ".",$row['M_BELOEP10']);
		}
		if (strlen($row['I_MAANEDUT11'])) {
			$periods[$row['I_MAANEDUT11']] = (float) str_replace(",", ".",$row['M_BELOEP11']);
		}
		if (strlen($row['I_MAANEDUT12'])) {
			$periods[$row['I_MAANEDUT12']] = (float) str_replace(",", ".",$row['M_BELOEP12']);
		}
		$first_period = false;
		foreach($periods as $key => $val) {
			if ($first_period === false) {
				$first_period = $key;
			}
			$periods[$key] = (int) ($val * 100);
		}
		
		$amount = (float) str_replace(",", ".",$row['M_BELOEP']);
		$interval_amount = $amount / $row['I_GIRORYTME'];
		$f_interval_amount = $interval_amount;
		$interval_amount = (int) (($interval_amount * 100) + 0.5);
		$interval = 12 / $row['I_GIRORYTME'];		
		
		$diff = false;
		$previous_month = false;
		$previous_amount = false;
		foreach($periods as $month => $period_amount) {
			if ($previous_month !== false && $previous_amount !== false) {
				$diff = $month - $previous_month;
				if ($diff != $interval || $period_amount != $interval_amount) {
					$custom = true;
				}
			}
			$previous_month = $month;
			$previous_amount = $period_amount;
		}
		
		$day = $row['I_TREKKDAG'];
		if (!strlen($day)) {
			$day = '1';
		}
		$params['start_date'] = '2013-'.$first_period.'-'.$day;
		$params['next_sched_contribution'] = '2013-'.$first_period.'-'.$day;
		$params['frequency_unit'] = 'month';
		$params['frequency_interval'] = $diff;
		$params['amount']  = $interval_amount;
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
	
	protected function findProduktType($id) {
		$sql = "SELECT * FROM `PMF_MAF_PRODUKTTYPE_txt` `p` WHERE `A_PRODUKTTYPE_ID` = '".$id."'";
		$stmnt = $this->pdo->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
		$stmnt->execute();
		return $stmnt->fetch();
	}
}