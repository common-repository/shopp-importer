<?php 
/**
  	Copyright: Copyright © 2010 Catskin Studio
	Licence: see index.php for full licence details
 */
?>
<?php  
/*
If PHP is not properly recognizing the line endings when reading files either on or created by a Macintosh computer, 
enabling the auto_detect_line_endings run-time configuration option may help resolve the problem.
*/
ini_set('auto_detect_line_endings',true);

class spi_files {
	function spi_files($spi) {
		$this->html_get_path = $spi->html_get_path;
		$this->column_map = $spi->column_map;
		$this->remove_from_description = $spi->remove_from_description;
		$this->csv_get_path = $spi->csv_get_path;
		$this->Shopp = $spi->Shopp;
		$this->spi = $spi;        
		
		
		 $this->delimiter = ','; 
			if($this->Shopp->Settings->get('catskin_importer_separator') == 'semicolon'){ 
				$this->delimiter = ';';     
			}
		
	}		
	
	function load_examine_csv($filename,$limit = false) {
		$row = 0;
		$examine_data = array();
		if (($handle = fopen($this->csv_get_path . $filename, "r")) !== FALSE) { 
				//echo '<pre>';
    		while (($read_row = fgetcsv($handle, 0, $this->delimiter)) !== FALSE) {  
						
        		if(!$this->emptyArray($read_row))$examine_data[] = $read_row;        		
        		$row++;
    		}
 				 //echo '</pre>';
    		fclose($handle);
		} else {
			$_SESSION["spi_error"] = "CSV file could not be loaded...";
		}
		return $examine_data;
	}
	
	function emptyArray($arr){
		foreach($arr as $val)
		{
		 	if(!empty($val)) return false;
		} 
		
		return true;
	}	

	function load_csv($filename, $start_at=1, $records=99999999,$has_header=true) {  
	 
		
		
		if ($has_header) {
			$row = 0;
		} else {
			$row = 1;
		}
		if (($handle = fopen($this->csv_get_path . $filename, "r")) !== FALSE) {
    		while (($read_row = fgetcsv($handle, 0, $this->delimiter)) !== FALSE) {
    			if ($row >= $start_at && $row < ($start_at + $records)) {
	        		if ($row > 0) {
	        			//exit(print_r($read_row,true));
	        		 if(!$this->emptyArray($read_row)) $data[] = $this->map_columns($read_row);
	        		}
    			}
        		$row++;
    		}
    		fclose($handle);
		} else {
			$_SESSION["spi_error"] = "CSV file could not be loaded...";
		}
		return $data;
	}	

	function map_columns($c_row) {
		
	}	
					
	function load_html_from_file($filename) {
		ob_start();
		readfile($this->html_get_path . $filename);
		$contents = ob_get_clean();
		foreach ($this->remove_from_description as $str) {
			$contents = str_replace($str,"",$contents);
		}
		return ltrim($contents);
	}	
}
?>