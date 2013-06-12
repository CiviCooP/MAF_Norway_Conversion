<?php

require_once('importer.php');

class importADRESSE extends importer {

	protected function getFilename() {
		return "PMF_MAF.ADRESSE.txt";
	}
	
	protected function getColumnDefinition($name) {
		$column = parent::getColumnDefinition($name);
		switch ($name) {
			case 'L_NAVN_ID':
				$column->is_primary = true;
				$column->type = "INT";
				$column->length = 10;
				break;
		}
		return $column;
	}
}