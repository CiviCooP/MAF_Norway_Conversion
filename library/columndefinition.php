<?php

class ColumnDefinition {
	
	public $name = "";
	
	public $type = "VARCHAR";
	
	public $is_null = true;
	
	public $default = "";
	
	public $length = 255;
	
	public $is_primary = false;
	
	public function __construct($name) {
		$this->name = trim($name);
		if (substr($name, 0,2) == "D_") {
			$this->type = "DATETIME";
			$this->length = "";
		}
	}
	
	public function getDefinition() {
		return sprintf("`%s` %s %s %s %s %s",
			$this->name,
			$this->type,
			$this->length ? "(".$this->length.")" : "",
			$this->is_null ? "NULL" : "NOT NULL",
			$this->is_primary ? "AUTO_INCREMENT PRIMARY KEY" : "",
			strlen($this->default) ? " DEFAULT '".$this->default."'" : ""
		);
	}

}