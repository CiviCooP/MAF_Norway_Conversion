<?php

require_once('importer.php');

class importAKSJON extends importer {

	protected function getFilename() {
		return "PMF_MAF.AKSJON.txt";
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