<?php

require_once('importer.php');

class importINNBETALINGSBUNKE extends importer {

	protected function getFilename() {
		return "PMF_MAF.INNBETALINGSBUNKE.txt";
	}
	
	protected function getColumnDefinition($name) {
		$column = parent::getColumnDefinition($name);
		switch ($name) {
			case 'L_INNBETALINGSBUNKE_ID':
				$column->is_primary = true;
				$column->type = "INT";
				$column->length = 10;
				break;
		}
		return $column;
	}
}