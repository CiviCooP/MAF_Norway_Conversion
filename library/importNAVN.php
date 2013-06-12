<?php

require_once('importer.php');

class importNAVN extends importer {

	protected function getFilename() {
		return "PMF_MAF.NAVN.txt";
	}
	
	protected function getColumnDefinition($name) {
		$column = parent::getColumnDefinition($name);
		switch ($name) {
			case 'L_NAVN_ID':
				$column->is_primary = true;
				$column->type = "INT";
				$column->length = 10;
				break;
			case 'A_KOMMENTAR':
			case 'A_DUPLIKATSAMLINGVALGTAV':
				$column->type = "TEXT";
				$column->length = false;
				break;
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
			$j = 0;
			for($i=0; $i < count($data1); $i++) {
				if ($i <= 14) {
					$data2[$i - $j] = $data1[$i];
				} elseif ($i == 15) {
					$j = -1;
					$data2[$i + $j] .= ";" . $data1[$i];
				} elseif ($i > 15) {
					$data2[$i + $j] = $data1[$i];
				}			
			}
			$data1 = $data2;
		}
		return $data1;
	}
}