<?php

require_once('importer.php');

class importAKTIVITET extends importer {

	protected function getFilename() {
		return "PMF_MAF.AKTIVITET.txt";
	}
	
	protected function getColumnDefinition($name) {
		$column = parent::getColumnDefinition($name);
		switch ($name) {
			case 'L_NAVN_ID':
				$column->is_primary = false;
				$column->type = "INT";
				$column->length = 10;
				break;
			case 'L_AKTIVITET_ID':
				$column->is_primary = false;
				$column->type = "INT";
				$column->length = 10;
				break;
		}
		return $column;
	}
}