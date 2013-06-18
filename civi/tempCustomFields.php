<?php

class tempCustomFields {
	
	protected $config;
	
	protected $pdo;
	
	protected $api;
	
	protected $fields;
	
	public function __construct(PDO $pdo, $api, $config) {
		$this->config = $config;
		$this->pdo = $pdo;
		$this->api = $api;
		
		$this->fields = array();
		
		$this->createFields();
	}
	
	protected function createFields() {
		/** 
	     * Create specific fields
	     */
		$params['name'] = 'maf_norway_import';
		$result = new stdClass();
		if ($this->api->CustomGroup->getsingle($params)) {
			$result = $this->api->result;
			$gid = $result->id;
		}
		if (!isset($result->id) || !$result->id) {
			unset($params);
			$params['name'] = 'maf_norway_import';
			$params['title'] = 'MAF Norway Import';
			$params['extends'] = 'Individual';
			$params['is_active'] = '1';
			$result = new stdClass();
			if ($this->api->CustomGroup->Create($params)) {
				$result = $this->api->result;
				$gid = $result->id;
			}
		}
		
		unset($params);
		$params['name'] = 'l_navn_id';
		$result = new stdClass();
		if ($this->api->CustomField->getsingle($params)) {
			$result = $this->api->result;
			$this->fields['l_navn_id'] = $result->id;
		}
		if (!isset($result->id) || !$result->id) {
			unset($params);
			$params['version']  = 3;
			$params['custom_group_id'] = $gid;
			$params['name'] = 'l_navn_id';
			$params['label'] = 'l_navn_id';
			$params['is_active'] = '1';
			$params['html_type'] = 'Text';
			$params['data_type'] = 'String';
			
			$result = new stdClass();
			if ($this->api->CustomField->Create($params)) {
				$result = $this->api->result;
				$this->fields['l_navn_id'] = $result->id;
			}
		}
	}
	
	public function getCustomField($name) {
		if (isset($this->fields[$name])) {
			return $this->fields[$name];
		}
		return null;
	}

}