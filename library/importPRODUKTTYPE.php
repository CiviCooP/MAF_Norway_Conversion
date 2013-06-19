<?php

require_once('importer.php');

class importPRODUKTTYPE extends importer {

	protected function getFilename() {
		return "PMF_MAF.PRODUKTTYPE.txt";
	}
	
	protected function getColumnDefinition($name) {
		$column = parent::getColumnDefinition($name);
		
		if ($column->type == "VARCHAR") {
			$column->length = 32;
		}
		return $column;
	}
	
	protected function prepareData($data) {
		/**
		 * Check the kommentar field (14) and add the next fields to next to it
		 */
		$data1 = $data;
		while (count($data1) > count($this->columns)) {
			$data2 = array();
			$i=0;
			foreach($data1 as $key => $value) {
				if ($key == 9 || $key == 10) {
					continue;
				} else {
					$data2[$i] = $value;
					$i++;
				}
			}
			$data1 = $data2;
		}
		return $data1;
	}
}