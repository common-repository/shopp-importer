<?php
/*
Plugin Name: Shopp Importer
Plugin URI: http://wpsmith.net/
Description: Shopp Product Importer Plugin provides a mechanisim to import Products from a specifically formatted CSV file into the shopp product database.
Version: 1.0.0
Author: Travis Smith, Tim Eijnden
Author URI: http://www.wpsmith.net/
License: GPLv2

    Copyright 2012  Travis Smith  (email : t@wpsmith.net)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

add_action( 'shopp_loaded', 'shopp_importer_init' );

function shopp_importer_init() {
	//Get helper classes      
	define( 'SHOPP_IMPORTER_DEBUG', false); 
	define( 'CATEGORY_TAGS_DELIMITER', '|' );    
	ini_set('max_execution_time', 600);

	include_once( 'spi_admin.php' );
	include_once( 'spi_data.php' );
	include_once( 'spi_db.php' );
	include_once( 'spi_files.php' );
	include_once( 'spi_images.php' );
	include_once( 'spi_model.php' );

	$spi = new shopp_product_importer();
}

class shopp_product_importer {
	//Shopp
	public $Shopp;
	//Maps
	public $column_map = null;
	public $variation_map = null;  
	public $spec_map = null;
	public $category_map = null;
	public $tag_map = null;
	public $image_map = null;
	//Data
	public $data = null;	
	public $options = array();	
	public $mapped_product_ids = array();
	//Paths
	public $html_get_path;
	public $csv_get_path; 
	public $image_put_path;
	public $basepath;
	public $path;
	public $directory;	
	//Html Removal
	public $remove_from_description = array(
		'<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"',
		'"http://www.w3.org/TR/html4/loose.dtd">',
		'<html>',
		'<head>',
		'<title>Untitled Document</title>',
		'face=SymbolMT size=5&gt;',
		'&#56256;&#56510;',
		'<spec http-equiv="Content-Type" content="text/html; charset=iso-8859-1">',
		'</head>',
		'<body>',
		'</body>',
		'</html>',	
	);	
	
	function spi_errors( $errno, $errstr, $errfile, $errline )
  	{
  		if ( dirname( $errfile ) == dirname( __FILE__ ) ) {
  			error_log( "Error: [$errno] $errstr in $errfile on $errline ", 0 );
  		}
  	}		
  	
  	function report_errors() {
		if ( isset( $_SESSION['spi_errors'] ) ) { 
			echo $_SESSION['spi_errors'];
			unset( $_SESSION['spi_errors'] );
		}  	
  	}
		
	function __construct() {
		global $Shopp;
		if ( class_exists( 'Shopp' ) ) {
			$this->Shopp = $Shopp;
			$this->set_paths();
			add_action( 'admin_menu', array( &$this, 'on_admin_menu' ) );
			add_action( 'wp_ajax_upload_spi_csv_file', array( &$this, 'ajax_upload_spi_csv_file' ) );
			add_action( 'wp_ajax_import_csv', array( &$this, 'ajax_import_csv' ) );
			add_action( 'wp_ajax_import_products', array( &$this, 'ajax_import_products' ) );
			add_action( 'wp_ajax_import_images', array( &$this, 'ajax_import_images' ) );
			add_action( 'wp_ajax_next_image', array( &$this, 'ajax_next_image' ) );	  		
			add_action( 'wp_ajax_import_status', array( &$this, 'ajax_import_status' ) );	  	
			set_error_handler( array( &$this, 'spi_errors' ) );
			$this->ajax_load_file();
		}	
	}

	function set_paths() {
		$this->basepath       = WP_PLUGIN_DIR;
		$this->path           = WP_PLUGIN_DIR . '/' . array_pop(explode( '/', dirname( __FILE__ ) ) );
		$this->directory      = basename( $this->path ); 
		$this->csv_get_path   = WP_CONTENT_DIR.'/csvs/';
		$this->html_get_path  = WP_CONTENT_DIR.'/product_htmls/';
		$this->image_put_path = WP_CONTENT_DIR.'/products/';
	}	
	
	function on_admin_menu( $args ) {	
		if ( SHOPP_VERSION < '1.1' ) {
			$page = add_submenu_page( $this->Shopp->Flow->Admin->default, __( 'Importer', 'Shopp' ), __( 'Importer', 'Shopp' ), SHOPP_USERLEVEL, "shopp-importer", array( &$this, 'shopp_importer_settings_page' ) );
			$spi_admin = new spi_admin( $this );
			$help_content = $spi_admin->set_help();
			unset( $spi_admin );
			add_contextual_help( $page, $help_content );
		}
		if ( SHOPP_VERSION >= '1.1' ) {
			global $wp_importers;
			register_importer( "shopp_product_importer2", "CSV Importer for Shopp 1.2.x", "CSV Import for Shopp 1.2.x", array( &$this,'shopp_importer_settings_page' ) );
			//exit("The Importers: ".print_r($wp_importers,true) );
		}
	} 
	
	function ajax_import_status()
	{
	   	$start_time = strtotime($_POST['start']); 
	    echo json_encode(array('products'=>self::get_product_count()));
		exit(); 
	}
	
	public static function get_product_count()
	{ 
		global $wpdb;     		
      	$query  = "select count(*) from `{$wpdb->prefix}posts` where `post_type` = 'shopp_product'";
		$result = $wpdb->get_var($query);
	    return $result;    
	}	   

	function shopp_importer_settings_page () {
		global $wpdb;
		
	   if ( ! empty( $_POST['get_status'] ) ) {  
		   echo 'hallo'; 
		   $start_time = strtotime($_POST['start']);

	       echo json_encode(array('products'=>$this->get_product_count()));
	       exit();
	  	}    
		
		if ( ! empty( $_POST['save'] ) ) {    
			
			//echo '<pre>'.print_r($_POST,1).'</pre>';
			check_admin_referer( 'shopp-importer' );
			if ( SHOPP_VERSION < '1.1' ) {
				$this->Shopp->Flow->settings_save();
			} else {
				$this->Shopp->Settings->saveform();
			}
			$updated = __( 'Importer settings saved.', 'spi' );
		}		
		if ( ! empty( $_POST['perform_import'] ) ) {
			check_admin_referer( 'shopp-importer' );
			set_time_limit( 86400 );
			$this->map_columns_from_saved_options();
			$this->ajax_load_file();
			$this->trunctate_all_prior_to_import();				
			global $importing_now;
			$importing_now = "perform_import";
			$updated       = __( 'Running Importer - Please Wait.....', 'spi' );
		}
		
	   
	
		include( "{$this->basepath}/{$this->directory}/settings.php" ); 
	}	

	
	function trunctate_all_prior_to_import() {
		global $wpdb;
		if ( $this->Shopp->Settings->get( 'catskin_importer_empty_first' ) == 'yes' ) {
			$query  = "	TRUNCATE TABLE `{$wpdb->prefix}shopp_price`;";
			$result = $wpdb->query($query);
			$query  = "	DELETE FROM `{$wpdb->prefix}posts` WHERE post_type = 'shopp_product'";
			$result = $wpdb->query($query);
			$query  = "	DELETE FROM `{$wpdb->prefix}shopp_meta` WHERE type='spec' OR type='image';";
			$result = $wpdb->query($query);	   
			$query  = "DELETE FROM `{$wpdb->prefix}terms` WHERE term_id in (SELECT term_id FROM `{$wpdb->prefix}term_taxonomy` WHERE taxonomy like '%shopp_%') "; 
			$result = $wpdb->query($query);	   
			$query  = "DELETE FROM `{$wpdb->prefix}term_taxonomy` WHERE taxonomy like '%shopp_%'"; 
            $result = $wpdb->query($query);
		}		
	}
	
	function clean_shopp_settings() {
		global $wpdb;
		$query  = "	DELETE FROM `{$wpdb->prefix}shopp_setting` WHERE `name` LIKE 'catskin%';";
		$result = $wpdb->query( $query );	
		
		if ( ! mysql_error() ) exit( "Shopp settings cleaned" ); else exit( mysql_error() );
		return false;
	}	
	
	function trunctate_prices_for_product($product_id) {
		global $wpdb;
		if ( $this->Shopp->Settings->get( 'catskin_importer_clear_prices' ) == 'yes' ) {
			$query = " DELETE FROM wp_shopp_price WHERE product='{$product_id}'";
			$result = $wpdb->get_var( $query );
		}		
	}	
	
	function quote_smart($value)
	{
	    // Quote if not a number or a numeric string
	    if (!is_numeric($value) ) {
	        $value = "'" . mysql_real_escape_string($value) . "'";
	    }
	    return $value;
	}		
	
	function ajax_import_csv() {
		global $wpdb;               
		
		//echo '<pre>'.print_r($this->column_map,1).'</pre>';
		$has_headers = $this->Shopp->Settings->get('catskin_importer_has_headers');
		$this->ajax_load_file();
		$query  = "  DROP TABLE IF EXISTS {$wpdb->prefix}shopp_importer; ";
		$result = $wpdb->query($query);
		$query  = "  CREATE TABLE {$wpdb->prefix}shopp_importer ( id INT NOT NULL AUTO_INCREMENT, PRIMARY KEY(id), product_id TEXT NULL, price_options TEXT NULL, price_optionkey TEXT NULL, price_label TEXT NULL, processing_status INT NULL DEFAULT 0 ";
			foreach ( $this->column_map as $key => $value ) 
			{ 
				$query .= ", spi_" . $key . " TEXT NULL"; 
			}
		$query .= " ) ";
		$result = $wpdb->query($query);
		
		$query        = " INSERT INTO {$wpdb->prefix}shopp_importer (";
		$column_index = 0;
		foreach ( $this->column_map as $key => $value ) { 
			if ( $column_index > 0 ) $query .= ", ";
			$query .= " spi_" . $key . " ";
			$column_index ++; 
		}
		$query .= ") VALUES ";
		if ( $has_headers ) array_shift( $this->examine_data );
		$row_index = 0;
		foreach ( $this->examine_data as $row ) {
			$field_index = 0;
			if ( $row_index > 0 ) $query .= ",";
			$query .= "(";
			foreach ( $this->column_map as $key => $value ) {
				if ( $field_index > 0 ) $query .= ", ";
				$query .= $this->quote_smart( $row[$value] ); 
				$field_index ++; 
			}			
			$query .= ") ";
			
			$row_index ++;
		}
		$query .= "; ";   
	    //echo $query;
		$result = $wpdb->query( $query );
		$this->map_columns_from_saved_options();
		$ajax_result = json_encode( $result );
		echo "<h2 style='border-bottom:1px dotted #333;'>CSV imported to database table {$wpdb->prefix}shopp_importer</h2>";
		exit();
	}
	
	function ajax_import_products() {
		global $wpdb, $Shopp,$first_id;
		$first_id_sql = "SELECT max(id) FROM {$wpdb->prefix}posts LIMIT 1"; 
	    $first_id     = $wpdb->get_var($wpdb->prepare($first_id_sql) );
		$model        = new spi_model($this);
		$model_result = $model->execute();
		echo $model->execute_mega_query();
		$this->report_errors();
		unset( $model );
		exit();  
		
	}
	
	function ajax_import_images() {
		global $wpdb, $Shopp;
		
		$model = new spi_model( $this );
		echo $model->execute_images();
		unset( $model );
		exit();
	}	
	
	function ajax_next_image() {
		$model = new spi_model( $this );
		echo $model->execute_images();
		$this->report_errors();
		unset( $model );		
		exit();
	}			
	
	function get_next_product() {
		global $wpdb;
		$query  = "SELECT * FROM {$wpdb->prefix}shopp_importer ORDER BY id limit 1";
		$result = $wpdb->get_row($query,OBJECT);
		return $result;
	}
	
	function get_next_set( $id ) {
		global $wpdb;
		$id     = trim( $id );
		$query  = "SELECT * FROM {$wpdb->prefix}shopp_importer WHERE spi_id = '{$id}' ORDER BY id ";
		$result = $wpdb->get_results( $query, OBJECT );
		return $result;
	}																						
	
	function ajax_upload_spi_csv_file() {	
		$csvs_path = realpath( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/csvs/';
		if ( file_exists( $csvs_path . $_POST['file_name'] ) ) {
			unlink( $csvs_path . $_POST['file_name'] );
		} 
		$file_name = $_POST['file_name'];
		$path_info = pathinfo( $_FILES[$upload_name]['name'] );
		if ( is_uploaded_file( $_FILES['csvupload']['tmp_name'] ) ) {
			if ( ! file_exists( $csvs_path ) ) mkdir( $csvs_path );
			chmod( $csvs_path, 0755 );
	   		$uploaded_file = file_get_contents( $_FILES['csvupload']['tmp_name'] );
	   		$handle = fopen( $csvs_path . $file_name, "w" );
	   		fwrite( $handle, $uploaded_file );
	   		echo "File uploaded successfully: " . $csvs_path.$file_name;
	   		fclose( $handle );
	   		
		} else {
			echo "The file didn't upload...";
		}	
					
		exit();
	}		
	
	function ajax_load_file() {
		$spi_files = new spi_files( $this );
		$filename  = $this->Shopp->Settings->get( 'catskin_importer_file' );
		if ( strlen( $filename ) != 0 && $filename != 'no-file-selected' ) {
			$this->examine_data = $spi_files->load_examine_csv( $filename, true );		
			$this->map_columns_from_saved_options();
			$start_at = 1;
			$has_headers = $this->Shopp->Settings->get( 'catskin_importer_has_headers' );
			$has_headers = ( $has_headers == 'yes' );
			$rows = $this->Shopp->Settings->get( 'catskin_importer_row_count' );
			if ( strlen( $filename ) > 0 && strlen( $rows ) > 0 && strlen( $has_headers ) > 0) { 
				$data = $spi_files->load_csv( $filename, $start_at, $rows, $has_headers );
				$_SESSION['spi_product_importer_data'] = $data;
				$spi_data = new spi_data( $this );
				$spi_data->map_product_ids();
				unset( $spi_data );
				
			} else {
				$error = "Could not load CSV";
			}
		}
		unset( $spi_files );
		return $data;	
	}
	
	function map_columns_from_saved_options() {
		//update mappings
		$this->column_map    = null;
		$this->variation_map = null;
		$this->tag_map       = null;
		$this->category_map  = null;
		$this->image_map     = null;
		$this->tax_map       = null;
		$tag_counter         = 0;
		$category_counter    = 0;
		$variation_counter   = 0;
		$image_counter       = 0;  
		$spec_counter        = 0; 
		$addon_counter       = 0;
		$tax_counter         = 0;
		$saved_column_map = $this->Shopp->Settings->get( 'catskin_importer_column_map' );
		for ( $col_index = 0; $col_index < $this->Shopp->Settings->get( 'catskin_importer_column_count' ); $col_index++ ) {
			switch ( $saved_column_map[$col_index]['type'] ) {
				case 'tag':
					$tag_counter++;
					$this->column_map[$col_index] = $saved_column_map[$col_index]['type'] . $tag_counter;
					$this->tag_map[]              = $saved_column_map[$col_index]['type'] . $tag_counter;
					break;
				case 'spec':
					//echo $saved_column_map[$col_index]['type'].'<br/>';
					$spec_counter++;
					$this->column_map[$col_index] = $saved_column_map[$col_index]['type'] . $spec_counter;
					$this->spec_map[]             = $saved_column_map[$col_index]['type'] . $spec_counter;   
					break;
				case 'category':
					$category_counter++;
					$this->column_map[$col_index] = $saved_column_map[$col_index]['type'] . $category_counter;
					$this->category_map[]         = $saved_column_map[$col_index]['type'] . $category_counter;	
					break;
				case 'image':
					$image_counter++;
					$this->column_map[$col_index] = $saved_column_map[$col_index]['type'] . $image_counter;
					$this->image_map[]            = $saved_column_map[$col_index]['type'] . $image_counter;	
					break; 
				case 'custom_tax':
					$this->column_map[$col_index] = $saved_column_map[$col_index]['type'].'-'.$saved_column_map[$col_index]['custom_tax'];
					$this->column_map[$col_index] = str_replace('-','_',$this->column_map[$col_index]);
					break;
				default:
					if ( strpos( $saved_column_map[$col_index]['type'], 'variation' ) !== false) {
						$variation_counter++;
						$this->column_map[$col_index] = $saved_column_map[$col_index]['type'] . $variation_counter;
						$this->variation_map[]        = $saved_column_map[$col_index]['type'] . $variation_counter;
					} elseif ( strpos( $saved_column_map[$col_index]['type'], 'addon' ) !== false ) {
						$addon_counter++;
						$this->column_map[$col_index] = $saved_column_map[$col_index]['type'] . $addon_counter;
						$this->variation_map[]        = $saved_column_map[$col_index]['type'] . $addon_counter;  			
					} else if ( '' != $saved_column_map[$col_index]['type'] ) {						
						$this->column_map[$col_index] = $saved_column_map[$col_index]['type'];
					}
					break;
			}
						
			
		}
		$this->column_map = array_flip( $this->column_map );			
	}	
}
