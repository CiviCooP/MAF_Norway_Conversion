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
	
	public function __construct(PDO $pdo, PDO $civi_pdo, $api, $config, $offset, $limit) {
		$this->config = $config;
		$this->pdo = $pdo;
		$this->civi_pdo = $civi_pdo;
		$this->api = $api;
		
		$this->util = new contactUtils($pdo, $civi_pdo, $api, $config);
		$this->tags = new importTagsGroups($pdo, $civi_pdo, $api, $config);
		
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
		if ($row['D_SLUTTDATO'] !== null) {
			$slutt = new DateTime($row['D_SLUTTDATO']);
			if ($slutt->format('Y') < 2013) {
				$doNotImport = true;
				echo "<span style=\"color: orange;\">D_SLUTTDATO: ".$slutt->format('d-m-Y')."</span><br />";
			}
		}
		
		$contactId = $this->util->getContactIdFromNavn($row['L_NAVN_ID']);
		if (!$contactId) {
			echo "<span style=\"color: red;\">Contact ".$row['L_NAVN_ID']." not found</span><br>";
			$doNotImport = true;
		}
		
		if (!strlen($row['M_BELOEP']) || $row['M_BELOEP'] == '0') {
			echo "<span style=\"color: orange;\">M_BELOEP: ".$row['M_BELOEP']."</span><br />";
			$doNotImport = true;
		}
		
		if ($doNotImport) {
			return false;
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
		$params['amount'] = (float) str_replace(",", ".", $row['M_BELOEP']);
		
		$params['frequency_day'] = $row['I_TREKKDAG'];
		if (!strlen($params['frequency_day'])) {
			$params['frequency_day'] = '1';
		}
		$params['create_date'] = $row['D_REGDATO'];
		
		//paid to be in periods
		$this->determinePeriods($params, $row);
		
		//determine payment method
		$this->determinPaymentMethod($params, $row);
		
		if ($row['D_SLUTTDATO'] !== null) {
			$slutt = new DateTime($row['D_SLUTTDATO']);
			$params['end_date'] = $slutt->format('Y-m-d');
		}
		
		if ($this->api->Pledge->Create($params)) {
			echo "<span style=\"color: green;\">Created pledge for contact ".$contactId.": ".$this->api->id."</span><br>";
			return true;
		}
		return false;
	}
	
	protected function determinPaymentMethod(&$params, $row) {
		$r = $this->findProduktType($row['A_PRODUKTTYPE_ID']);
		$type = $r['A_PRODUKTTYPENAVN'];
		$createNew = true;
		if ($this->api->FinancialType->get(array('name' => $type))) {
			if ($this->api->count > 1) {
				$params['pledge_financial_type_id'] = $this->api->values[0]->id;
				$createNew = false;
			} elseif ($this->api->count == 1) {
				$params['pledge_financial_type_id'] = $this->api->id;
				$createNew = false;
			}
		} 
		if ($createNew) {
			$create['name'] = $type;
			$create['is_active'] = '1';
			$create['is_reserved'] = '0';
			$create['is_deductible'] = '0';
			
			if ($this->api->FinancialType->Create($create)) {
				$params['pledge_financial_type_id'] = $this->api->id;
			}
		}
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
		$params['frequency_unit'] = 'month';
		$params['frequency_interval'] = $diff;
		$params['installments'] = count($periods);
	}
}