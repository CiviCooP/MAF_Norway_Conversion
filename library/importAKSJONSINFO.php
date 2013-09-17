<?php

require_once('importer.php');

class importAKSJONSINFO extends importer {

	protected function getFilename() {
		return "PMF_MAF.AKSJONSINFO.txt";
	}
	
	protected function getColumnDefinition($name) {
		$column = parent::getColumnDefinition($name);
		switch ($name) {
			case 'L_AKSJON_ID':
				$column->is_primary = false;
				$column->type = "INT";
				$column->length = 10;
				break;
		}
		return $column;
	}
}