<?php 
/**
  	Copyright: Copyright © 2010 Catskin Studio
	Licence: see index.php for full licence details
 */

include_once('../shopp/api/product.php');    


?> 

<div class="wrap shopp">
<?php	
	if (isset($_GET['clean'])) {
		if ($_GET['clean'] == 'clean') {
			$this->clean_shopp_settings();
		}
	}
	global $Shopp;	
	$shopp_data_version = (int)$Shopp->Settings->get('db_version');
	$shopp_first_run = $Shopp->Settings->get('display_welcome');
	$shopp_setup_status = $Shopp->Settings->get('shopp_setup');	
	$shopp_maintenance_mode = $Shopp->Settings->get('maintenance');	
	
	//Maintenance Message??? 1.1 dev
	if (SHOPP_VERSION >= '1.1') {
		if ($data_version >= 1100 || $shopp_first_run != "off") {
			exit("<h2>Shopp Product Importer</h2><p>Complete Shopp installation prior to importing CSV's.</p>");
			return false;
		}
	} else {
		if ($shopp_setup_status != "completed" || $shopp_maintenance_mode != "off" || $shopp_first_run != "off") {
			exit("<h2>Shopp Product Importer</h2><p>Complete Shopp installation prior to importing CSV's.</p>");
			return false;
		}
	}
	
	$has_error = false;
	$uuid = uniqid();  	
	if (isset($_FILES["csvupload"])) {
  		$nonce=$_REQUEST['_wpnonce'];
  		if (! wp_verify_nonce($nonce, 'shopp-importer') ) die('Security check'); 
		$upload_name = "csvupload";
		$max_file_size_in_bytes = 2147483647;				
		$extension_whitelist = array("csv", "txt");	
		$valid_chars_regex = '.A-Z0-9_ !@#$%^&()+={}\[\]\',~`-';				
	
		$MAX_FILENAME_LENGTH = 260;
		$file_name = "";
		$file_extension = "";
		$uploadErrors = array(
	        0=>"There is no error, the file uploaded with success.",
	        1=>"The uploaded file exceeds the upload_max_filesize directive in php.ini",
	        2=>"The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.",
	        3=>"The uploaded file was only partially uploaded.",
	        4=>"No file was uploaded.",
	        6=>"Missing a temporary folder."
		);
	
		if (!isset($_FILES["csvupload"])) {
			$updated = "No upload found in \$_FILES for " . $upload_name;
			$has_error = true;
		} else if (isset($_FILES["csvupload"]["error"]) && $_FILES[$upload_name]["error"] != 0) {
			$updated = $uploadErrors[$_FILES["csvupload"]["error"]];
			$has_error = true;
		} else if (!isset($_FILES["csvupload"]["tmp_name"]) || !@is_uploaded_file($_FILES[$upload_name]["tmp_name"])) {
			$updated = "Upload failed is_uploaded_file test.";
			$has_error = true;
		} else if (!isset($_FILES["csvupload"]['name'])) {
			$updated = "File has no name.";
			$has_error = true;
		} else {
			//echo " ALL GOOD SO FAR!!!";
		}
	
		$file_size = @filesize($_FILES["csvupload"]["tmp_name"]);
		if (!$file_size || $file_size > $max_file_size_in_bytes) {
			$updated = "File exceeds the maximum allowed size.";
			$has_error = true;
		} else {
			//echo " ALL GOOD SO FAR!!!";
		}
	
		if ($file_size <= 0) {
			$updated = "File size outside allowed lower bound.";
			$has_error = true;
		} else {
			//echo " ALL GOOD SO FAR!!!";
		}
	
		$file_name = preg_replace('/[^'.$valid_chars_regex.']|\.+$/i', "", basename($_FILES["csvupload"]['name']));
		if (strlen($file_name) == 0 || strlen($file_name) > $MAX_FILENAME_LENGTH) {
			/*seems to come through even when not uploading a file */
			//$updated = "Invalid file name (*)";
			$updated = "";
			$has_error = true;
		} else {
			//echo " ALL GOOD SO FAR!!!";
		}
		
		if (!$has_error) {
			$csvs_path = realpath(dirname(dirname(dirname(__FILE__)))).'/csvs/';
			if (file_exists($csvs_path.$file_name)) {
				unlink($csvs_path.$file_name);
			}
	
			$path_info = pathinfo($_FILES[$upload_name]['name']);
			$file_extension = $path_info["extension"];
			$is_valid_extension = false;
			foreach ($extension_whitelist as $extension) {
				if (strcasecmp($file_extension, $extension) == 0) {
					$is_valid_extension = true;
					break;
				}
			}
			
			if (!$is_valid_extension) {
				$updated = "Invalid file extension.";
			}
		
			if (is_uploaded_file($_FILES['csvupload']['tmp_name'])) {
				if (!file_exists($csvs_path)) mkdir($csvs_path);
				chmod($csvs_path,0755);
		   		$uploaded_file = file_get_contents($_FILES['csvupload']['tmp_name']);
		   		$handle = fopen($csvs_path.$file_name, "w");
		   		fwrite($handle, $uploaded_file );
		   		$updated = "File uploaded successfully: ".$csvs_path.$file_name;
		   		fclose($handle);
		   		$this->Shopp->Settings->save('catskin_importer_file',$file_name);		   		
			} else {
				echo "The file didn't upload...";
			}		

	  	} else {
	  		//Don't worry we aren't trying to upload a file in this case
	  	}
	  	
  	} 	
	
 ?>
	<?php if (!empty($updated)): ?><div id="message" class="updated fade"><p><?php echo $updated; ?></p></div><?php endif; ?>

	<form enctype="multipart/form-data" name="settings" id="general" action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>" method="post">
		<?php wp_nonce_field('shopp-importer'); ?>
	<div id="main-container" style="position:relative; width:100%; float:left; line-height:200%;">
		<div style="float:right; line-height:160%;">
			<img src="<?php echo WP_PLUGIN_URL."/".$this->directory; ?>/images/shopp_product_importer_csv_logo.png" style="border:0px; position:relative; display:block; margin:12px 4px;"/><p>&nbsp;</p>
			
			<div style="background:#f33; color:#fff; font-weight:bold; font-size:13px; border-radius: 10px; padding:5px; margin:-10px 5px 5px 5px; width:266px;"><div style="background:#fff; color:#f00; border-radius:3px; padding:6px;">WARNING:</div><div style="padding:10px;">It is <em>HIGHLY</em> recommended that you make a backup of your database and your site before importing any products. This plugin is not guaranteed by shopplugin.net and to be used at your own risk. <u>Disable this plugin</u> once you are finished importing your products as this plugin has not yet been audited. If you would like to sponsor an audit, please contact <a href="mailto: t@wpsmith.net">me</a>.</div></div>		
			<div id="todo-list-container" style="display:none; position:relative; background:#ffa; font-weight:bold; font-size:13px; border-radius: 10px; padding:5px; margin:5px; width:266px;">
				<div style="background:#fff; color:#333; border-radius:3px; padding:6px;">TO-DO LIST:</div>
				<ol id="todo-list" style="font-weight:bold; line-height:210%; margin:10px; list-style:inside;">
					<li>Security Audit</li>
				</ol>
			</div> 
			<?php if(ini_get('max_execution_time') == 30) : ?>
				<br/><div style="background:#f33; color:#fff; font-weight:bold; font-size:13px; border-radius: 10px; padding:5px; margin:-10px 5px 5px 5px; width:266px;"><div style="background:#fff; color:#f00; border-radius:3px; padding:6px;">WARNING:</div><div style="padding:10px;">The maximum time for the importer to run is 30 seconds. This may not be enough time to import all your products. An attempt to change the PHP setting 'max_execution_time' has been made but without success. Please contact your hosting provider.
				</div>	
			</div>                                                
			<?php endif ?>
			   
		</div>  
		 

		
		    
		<h2><?php _e('Shopp Product Importer','Shopp'); ?></h2>
		<p><?php _e('Click the help link for instructions and samples.	','Shopp'); ?><br/></p>
		<div style="margin:5px 300px 5px 0px; padding:20px; background:#fff; border-radius:10px;">
			<table cellspacing="4">
				<tr>
					<td style="padding:5px; width:300px; vertical-align:top;border-bottom:1px solid #fff;"><b><label for="catskin_importer_file"><?php _e('CSV to import','Shopp'); ?>: </label></b></td>
					<td style="padding:5px; width:auto; vertical-align:top;border-bottom:1px solid #fff;"><input type="hidden" name="MAX_FILE_SIZE" value="300000" />
		<?php 
			if ($handle = opendir($this->csv_get_path)) {
			    echo "<select name='settings[catskin_importer_file]' onchange='javascript:(function($){update_required();})(jQuery);' >";
			    echo "<option value='no-file-selected'>Select File</option>";
				
			    /* This is the correct way to loop over the directory. */
			    while (false !== ($file = readdir($handle))) {
					$path_parts = pathinfo($this->csv_get_path.$file);
					if ($path_parts['extension'] == 'csv') {			    	
				    	if (attribute_escape($this->Shopp->Settings->get('catskin_importer_file')) == $file) { $selected = ' selected="selected" '; } else { $selected = ''; }
				        echo "<option value='$file' $selected>$file</option>\n";
			        }
			    }
			
			    /* This is the WRONG way to loop over the directory. */
			    while ($file = readdir($handle)) {
			        echo "$file\n";
			    }
			 	echo "</select>";
			    closedir($handle);
			} else {
			    echo "<select name='settings[catskin_importer_file]' onchange='javascript:(function($){update_required();})(jQuery);' >";
			    echo "<option value='no-file-selected'>Select File</option>";
			 	echo "</select>";					
			}
		?>
		<?php _e("list contains csv's uploaded to <b>/wp-content/csvs/</b> folder.",'Shopp'); ?></td>
				</tr>
				<tr>
					<td style="padding:5px; width:300px; vertical-align:top; border-bottom:1px solid #fff;"><b><label for="csvupload"><?php _e('Upload a CSV','Shopp'); ?>: </label></b></td>
					<td style="padding:5px; width:auto; vertical-align:top;border-bottom:1px solid #fff;"><input type="file" name="csvupload"  onchange="javascript:(function($){update_required();})(jQuery);" /> <br/><i><?php _e('If the upload utility does not save your CSV file to the list, create the <b>/wp-content/csvs/</b> folder and FTP your CSV file to that location. Refresh this page and the CSV should then be available to use.','Shopp'); ?></i></td>
				</tr>
				<tr>
					<td style="padding:5px; width:300px; vertical-align:top;"><b><label><?php _e('CSV contain a header row','Shopp'); ?>: </label></b></td>
					<td style="padding:5px; width:auto; vertical-align:top;"><input type="hidden" name="settings[catskin_importer_has_headers]" value="no" /><input type="checkbox" name="settings[catskin_importer_has_headers]" value="yes" id="catskin_importer_has_headers"<?php if ($this->Shopp->Settings->get('catskin_importer_has_headers') == "yes") echo ' checked="checked"'?> onchange="javascript:update_required();"/><label for="catskin_importer_has_headers"></label></td>
				</tr>
				<tr>
					<td style="padding:5px; width:300px; vertical-align:top;"><b><label><?php _e('CSV Delimiter','Shopp'); ?>: </label></b></td>
					<td style="padding:5px; width:auto; vertical-align:top;"><select name="settings[catskin_importer_separator]" id="catskin_importer_separator" onchange="javascript:update_required();">
					<option value="comma" <?php if ($this->Shopp->Settings->get('catskin_importer_separator') == "comma") echo 'selected' ?>>Comma (,)</option>
					<option value="semicolon" <?php if ($this->Shopp->Settings->get('catskin_importer_separator') == "semicolon") echo 'selected' ?>>semicolon (;)</option>
					</select> <label for="catskin_importer_separator"></label></td>
				</tr>
				<tr>
					<td style="padding:5px; width:300px; vertical-align:top;"><b><label><?php _e('Image path','Shopp'); ?>:</label></b></td>
					<td style="padding:5px; width:auto; vertical-align:top;"><label for="catskin_importer_imageformat"><input name="settings[catskin_importer_imageformat]" value="<?php echo $this->Shopp->Settings->get('catskin_importer_imageformat') ?>" id="catskin_importer_imageformat" onchange="javascript:update_required();" size="70">
					</label> <small>eg: <?php echo ABSPATH ?>img/{val}.jpg</small></td>
				</tr>				
			</table>		
			<input type="hidden" name="settings[catskin_importer_empty_first]" value="no" /><input type="checkbox" name="settings[catskin_importer_empty_first]" value="yes" id="catskin_importer_empty_first"<?php if ($this->Shopp->Settings->get('catskin_importer_empty_first') == "yes") echo ' checked="checked"'?> onchange="javascript:update_required();" /><label for="catskin_importer_empty_first"> <?php _e('Empty existing products prior to import?','Shopp'); ?></label>
		</div>
		<div style=" padding:10px; margin:15px 0px; background:#fff; border:1px solid #999;-moz-border-radius:8px;-webkit-border-radius:8px; color:#000; clear:both;">
			<div style="float:right;" class="ready"><input type="submit" class="button-primary" id="run-spi-import-now" name="perform_import" value="<?php _e('Run Importer','Shopp'); ?>" /></div>
			<div style="float:left; display:none;" class="needs-update"><input type="submit" class="button-primary" name="save" value="<?php _e('Update Importer Settings','Shopp'); ?>" /></div>
			<div style="clear:both;"></div>
		</div>
		
		<div id="spi-show-when-importing" style="display:none;">
			<div>
				<div><div id="spi-ajax-loader" style="float:left;"><div style="float:left;"><img src="<?php echo get_option('siteurl').'/wp-content/plugins/'.$this->directory.'/ajax-loader.gif'?>" /></div><div id="progressbar" style="float:left;"></div></div></div>
				<div id="imported-rows" style="-moz-border-radius:8px;-webkit-border-radius:8px;-moz-box-shadow: 1px 1px 3px #000; -webkit-box-shadow: 1px 1px 3px #000; border:1px solid #bbb;font-size:12px; font-weight:bold; padding:3px; margin:2px; background:#eee; color:#000; clear:both; padding:10px;"></div>
			</div>
		</div>
		<?php if (!isset($importing_now) && ($this->Shopp->Settings->get('catskin_importer_file') != 'no-file-selected') && strlen($this->Shopp->Settings->get('catskin_importer_file')) != 0): ?>
			<?php 
				unset($_SESSION['spi_product_importer_data']);
				unset($_SESSION['spi_mapped_product_ids']);
			?>
			<?php 
				function doselector($column_number,$object_context) {
					$existing_map = $object_context->Shopp->Settings->get('catskin_importer_column_map');
					$output = "";
					$catskin_importer_type = $existing_map[$column_number]['type'];	
					$output .= "<select class='field-type-selector' name='settings[catskin_importer_column_map][{$column_number}][type]'  onchange='javascript:update_required();'>";
					$output .= "<option value='' ".(''==$catskin_importer_type?'selected=selected':'').">Don't Import</option>";			
					$output .= "<option value='id' ".('id'==$catskin_importer_type?'selected=selected':'').">Product Identifier</option>";			
					$output .= "<option value='category' ".('category'==$catskin_importer_type?'selected=selected':'').">Category(s) [Pipe | Delimited Text]</option>";			
					$output .= "<option value='descriptiontext' ".('descriptiontext'==$catskin_importer_type?'selected="selected"':'').">Description [Text]</option>";
					$output .= "<option value='featured' ".('featured'==$catskin_importer_type?'selected="selected"':'').">Featured [ON,OFF] (*auto OFF)</option>";
					$output .= "<option value='image' ".('image'==$catskin_importer_type?'selected="selected"':'').">Image ID [Number] (use Image path option)</option>";	
					$output .= "<option value='inventory' ".('inventory'==$catskin_importer_type?'selected="selected"':'').">Inventory [ON,OFF] (*auto OFF)</option>";							
					$output .= "<option value='name' ".('name'==$catskin_importer_type?'selected="selected"':'').">Name [Text]</option>";
					$output .= "<option value='price' ".('price'==$catskin_importer_type?'selected="selected"':'').">Price [Text]</option>";							
					$output .= "<option value='published' ".('published'==$catskin_importer_type?'selected="selected"':'').">Published [ON,OFF] (*auto ON)</option>";		
					$output .= "<option value='sale' ".('sale'==$catskin_importer_type?'selected="selected"':'').">Sale [ON,OFF] (*auto OFF) (requires sale price)</option>";							
					$output .= "<option value='saleprice' ".('saleprice'==$catskin_importer_type?'selected="selected"':'').">Sale Price [Text] (requires sale)</option>";							
					$output .= "<option value='shipfee' ".('shipfee'==$catskin_importer_type?'selected="selected"':'').">Shipping Fee [Text]</option>";	
					$output .= "<option value='sku' ".('sku'==$catskin_importer_type?'selected="selected"':'').">SKU [Text] (requires stock)</option>";
					$output .= "<option value='stock' ".('stock'==$catskin_importer_type?'selected="selected"':'').">Stock Quantity [Number] (requires sku)</option>";							
					$output .= "<option value='slug' ".('slug'==$catskin_importer_type?'selected="selected"':'').">Slug [Text] (*auto product-name)</option>";
					$output .= "<option value='summary' ".('summary'==$catskin_importer_type?'selected="selected"':'').">Summary [Text]</option>";
					$output .= "<option value='tag' ".('tag'==$catskin_importer_type?'selected="selected"':'').">Tag(s) [Pipe | Delimited Text]</option>";
					$output .= "<option value='tax' ".('tax'==$catskin_importer_type?'selected="selected"':'').">Tax [ON,OFF] (*auto ON)</option>";							
					$output .= "<option value='price_type' ".('price_type'==$catskin_importer_type?'selected="selected"':'').">Type [Shipped,Virtual,Download,Donation,N/A] (*auto Shipped)</option>";
					$output .= "<option value='shipping' ".('shipping'==$catskin_importer_type?'selected="selected"':'').">Shipping [ON,OFF] (*auto ON)</option>";
					$output .= "<option value='variation_price' ".('variation_price'==$catskin_importer_type?'selected="selected"':'').">Variation price [Number]</option>";
					$output .= "<option value='variation_taxed' ".('variation_taxed'==$catskin_importer_type?'selected="selected"':'').">Variation taxed [ON,OFF]</option>";
					$output .= "<option value='addon_price' ".('addon_price'==$catskin_importer_type?'selected="selected"':'').">Add-on price [Number]</option>";
					$output .= "<option value='addon_taxed' ".('addon_taxed'==$catskin_importer_type?'selected="selected"':'').">Add-on taxed [ON,OFF]</option>";
					$output .= "<option value='spec' ".('spec'==$catskin_importer_type?'selected="selected"':'').">Spec [Text]</option>"; 
					$output .= "<option value='weight' ".('weight'==$catskin_importer_type?'selected="selected"':'').">Weight [Text]</option>";
					
					$taxonomies = get_object_taxonomies( Product::$posttype, 'names' );				
					if ( ! empty( $taxonomies ) ) {
						$output .= "<option value='custom_tax' ".('custom_tax'==$catskin_importer_type?'selected="selected"':'').">Custom Taxonomy [Pipe | Delimited Text]</option>";
					}
					$output .= "</select>";		
					$output .= "<input type='text' class='catskin_variation_group_editor' id='catskin_importer_column_map_group_{$column_number}' name='settings[catskin_importer_column_map][{$column_number}][group]' value='".$existing_map[$column_number]['group']."'  style='display:none;'  onchange='javascript:update_required();'/><label for='settings[catskin_importer_column_map][{$column_number}][group]' style='display:none;'>Variation Group</label>";
					$output .= "<input type='text' class='catskin_variation_label_editor' id='catskin_importer_column_map_label_{$column_number}' name='settings[catskin_importer_column_map][{$column_number}][label]' value='".$existing_map[$column_number]['label']."'  style='display:none;'  onchange='javascript:update_required();'/><label for='settings[catskin_importer_column_map][{$column_number}][label]' style='display:none;'>Variation Name</label>";     

					if ( ! empty( $taxonomies ) ) {
							$output .= '<span class="select-tax"><label for="custom_tax_{$column_number}">Taxonomy</label>';
							$output .= "<select id='custom_tax_{$column_number}' name='settings[catskin_importer_column_map][{$column_number}][custom_tax]' >";
							$output .= "<option value='' ".('custom_tax'==$existing_map[$column_number]['custom_tax']?'selected="selected"':'').">" . __( 'Select', 'spi' ) . "</option>";
						foreach( $taxonomies as $taxonomy ) {
							if ( 'shopp_category' == $taxonomy || 'shopp_tag' == $taxonomy ) continue;
							$t = get_taxonomy( $taxonomy );
							$output .= "<option value='{$t->name}' ".($t->name==$existing_map[$column_number]['custom_tax']?'selected="selected"':'').">{$t->label}</option>";
						}
						$output .= "</select>";
						$output .= '</span>';          
						
					}
					return $output;
				}
				$filename = $this->Shopp->Settings->get('catskin_importer_file');
				$spi_files = new spi_files($this);
				$this->examine_data = $spi_files->load_examine_csv($filename,true);
					
				unset($spi_files); 
				$_SESSION['row_count'] = '';
				if (isset($this->examine_data) && strlen($filename) > 0) {
					if (is_array($this->examine_data)) {
						$row_count = count($this->examine_data);
						if ($this->Shopp->Settings->get('catskin_importer_has_headers') == "yes") $row_count--;
						$col_count = count($this->examine_data[0]);
						$_SESSION['row_count'] = $row_count;
						$this->Shopp->Settings->save('catskin_importer_row_count',$row_count);
						$this->Shopp->Settings->save('catskin_importer_column_count',$col_count);
						$this->ajax_load_file();
						echo "<p>Rows in file: ".$row_count."</p>";
						
						echo "<table cellspacing='0' style='border:1px solid #999; width:100%;-moz-border-radius:8px;-webkit-border-radius:8px;-moz-box-shadow: 1px 1px 3px #000; -webkit-box-shadow: 1px 1px 3px #000; border:1px solid #bbb;font-size:12px; font-weight:bold; padding:3px; margin:2px; background:#eee; color:#000; clear:both; padding:10px; line-height:100%;'><tr><td style='border-bottom:1px solid #999; color:#fff; background:#BBB; padding:5px;'>Cols</td><td style='border-bottom:1px solid #999; color:#fff; background:#BBB;  padding:5px;'>First Row</td><td style='border-bottom:1px solid #999; color:#fff; background:#BBB;  padding:5px;'>Column Mapping</td><td style='border-bottom:1px solid #999; color:#fff; background:#BBB;  padding:5px;'>Second Row</td></tr>";
						$col_counter = 0;
						for ($i = 0; $i < count($this->examine_data[0]); $i++) {
							$col_counter++;
							echo "<tr><td style='border-bottom:1px solid #ccc; padding:5px; background:#eee;'><span style='color:#999;'>Col. {$col_counter}:</span></td><td style='border-bottom:1px solid #ccc; padding:5px; background:#fff;'><b>".$this->examine_data[0][$i]."</b></td><td style='border-bottom:1px solid #ccc; padding:5px; background:#eee;'>". doselector($i,$this)."</td><td style='border-bottom:1px solid #ccc; padding:5px; background:#fff;'><span style='color:#999;'>Next Row Data :</span> ".$this->examine_data[1][$i]."</td></tr>";
						}
						echo "</table>";
						?>
								<div style=" padding:10px; margin:15px 0px; background:#fff; border:1px solid #999;-moz-border-radius:8px;-webkit-border-radius:8px; color:#000; clear:both;">
									<div style="float:right;" class="ready"><input type="submit" class="button-primary" id="run-spi-import-now" name="perform_import" value="<?php _e('Run Importer','Shopp'); ?>" /></div>
									<div style="float:left;" class="needs-update"><input type="submit" class="button-primary" name="save" value="<?php _e('Update Importer Settings','Shopp'); ?>" /></div>
									<div style="clear:both;"></div>
								</div>					
						<?php
					}	
				}?>
			<?php elseif (isset($importing_now)): ?>
			<script type="text/javascript">
			(function($){       
				
				window.SHOPP_IMPORTER_DEBUG = <?php if(SHOPP_IMPORTER_DEBUG) echo SHOPP_IMPORTER_DEBUG; else echo 0  ?>;
				window.rowCount = <?php echo $_SESSION['row_count'] ?>;
				window.startProductCount = <?php echo shopp_product_importer::get_product_count() ?>;

				function view_start() {
					$('#spi-show-when-importing').show('fast');
					$('#run-spi-import-now').attr("value","Please Wait... The importer is running");
					$(".needs-update > input").attr("value","Emergency Stop!");		
					$(".needs-update > input").css("background","#a00");			
					$('.needs-update').show(100);  
				                          
					
				}
				
				function view_finish() {
					$(".needs-update > input").attr("value","All Done");		
					$(".needs-update > input").css("background","#0a0");			
				}				

				function view_requesting() {
					$('#spi-ajax-loader').show('slow');

					$('#imported-rows').append("<div style='clear:both;'></div>");				
				}		

				function view_error() {
					$('#spi-ajax-loader').hide('slow');
					$('#run-spi-import-now').attr("value","An Error Occured... Update Importer Settings before trying again...");
					$('#spi-show-when-importing').hide('fast');			
				}

				function view_after_all_rows_imported() {
					$('#spi-ajax-loader').hide('slow');	
					$('#importing-rows').prepend("<div>Import Complete!</div>").show('slow');
					$('#run-spi-import-now').hide('slow');
					view_finish();
				}
				
				function import_csv() {
					view_start();
					view_requesting();
					var maindata = {
							action: 'import_csv'
					};					
				           
			   		var statusData = {};
			        statusData['action'] = 'import_status'	;
			        statusData['start'] = Date();
			        statusData['get_status'] = true;
					   
					function getStatus()
					{
						jQuery.ajax({
							type: "POST", 
							url: ajaxurl, 
							data: statusData, 
							dataType: 'json',
							success: function(response){ 
								$("#status").text((parseInt(response.products)-parseInt(window.startProductCount)));						
							}
						})    
					}
						
						
					window.statusInterval = setInterval(getStatus, 5000);     
					
					 jQuery.ajax({
							type: "POST",
							url: ajaxurl, 
							data: maindata,      
							success: function(response){
								$("#imported-rows").append(response); 
								import_products();  

							},
							error: function(response){
								if(SHOPP_IMPORTER_DEBUG) console.log(response);
								view_error(); 
							}
						});
					
				}     
				var product_count = 0;
				var product_index = 0;
				var products_id_mapping;
				
				function import_products() {
					var maindata = {
							action: 'import_products'
					};	 
					
				    $('#imported-rows').append("Status: <span id='status'>0</span><span> / "+window.rowCount+"</span><div style='clear:both;'></div>");    		
					jQuery.ajax({
						type: "POST",
						url: ajaxurl, 
						dataType: SHOPP_IMPORTER_DEBUG ? 'html' : 'json',  
						data: maindata, 
						success: function(response){     
							if(SHOPP_IMPORTER_DEBUG){
    							$("#imported-rows").append(response);
								view_after_all_rows_imported();   
								clearInterval(window.statusInterval);  
							}
							products_id_mapping = response.products;
							product_count = response.count;
							$("#status").text((parseInt(product_count)-parseInt(window.startProductCount)));    
							$("#imported-rows").append("<h3>Imported products</h3>");    	
							$("#imported-rows").append("<p>Added "+response.count+" products</p>");
							$("#imported-rows").append("<h3>Uploading Images...</h3>");
							$("#imported-rows").append("<p>Sit back, relax, grab a coffee...</p>");
							if(response.has_image) next_image();
							else{ 
								$("#imported-rows").append("<div style='clear:both;'></div><p>There are no images to upload from this CSV file</p><p><b>Congratulations! Your CSV has been imported.</b></p>");
								view_after_all_rows_imported();  
								clearInterval(window.statusInterval);
							}  
							             
						      
						},
						error: function(response){ 
							if(SHOPP_IMPORTER_DEBUG) console.log(response);
							view_error();        
							clearInterval(window.statusInterval); 
						}
					});
				}	
				
				function next_image() {
					var maindata = {
							action: 'next_image',
							status: 20,
							mapping: products_id_mapping[product_index]
					};					
					jQuery.ajax({
						type: "POST",
						url: ajaxurl, 
						data: maindata, 
						success: function(response){ 
							if(product_index < product_count){
							 	$("#imported-rows").append(response);
							 	product_index++;
								next_image();
							} else {
								$("#imported-rows").append("<div style='clear:both;'></div><p>There are no more images to upload from this CSV file</p><p><b>Congratulations! Your CSV has been imported, images and all...</b></p>");
								view_after_all_rows_imported();
							}  
						},
						error: function(response){
							if(SHOPP_IMPORTER_DEBUG) console.log(response);
							view_error();
						}
					});
				}												
				import_csv();
			})(jQuery);
			</script>	
		<?php else:?>
			<?php 
				unset($_SESSION['spi_product_importer_data']);
				unset($_SESSION['spi_mapped_product_ids']);
			?>		
			<script type="text/javascript">
				(function($){
					jQuery('#run-spi-import-now').hide();			
				})(jQuery);	
			</script>				
	    <?php endif; ?>
	    </div>
	</form>
</div>

<script type="text/javascript">
	function validate() {
		var validates = true;
		if (jQuery("select[name*='catskin_importer_file']").val() == 'no-file-selected'){
			validates = false;
			jQuery("#todo-list").append("<li>Upload or choose a CSV file to import.</li>");
			
		}
		var has_html_files = false;
		var path_exists = "<?php echo file_exists($this->html_get_path)?"YES":"NO"; ?>"
		if (path_exists === "NO"){
			jQuery("option[value='description']").each(function() {
				if (jQuery(this).attr("selected") == true || jQuery(this).attr("selected") == 'selected') {
					has_html_files = true;
				}
			});	
			if (has_html_files) {
				validates = false;
				jQuery("#todo-list").append("<li>You need to specifiy the location of your uploaded HTML product description files, the one listed in 'Path to HTML files' doesn't exist.</li>");
			}	
		}
			
		var has_id = 0;   
		var has_sku = 1;  
		
		jQuery("option[value='id']").each(function() {
			if (jQuery(this).attr("selected") == true || jQuery(this).attr("selected") == 'selected') {
				has_id = has_id + 1;
			}
		});	
		if (has_id === 0) {
			validates = false;
			jQuery("#todo-list").append("<li>One column must contain a PRODUCT Identifier : The Product Identifier must be unique to the Product. If the product has Variations all variations should share the same Product Identifier.</li>");
		} else if (has_id > 1) {
			validates = false;
			jQuery("#todo-list").append("<li>Your column map contains multiple SKU columns.</li>");		
		}
		/*		
		var has_sku = 0;
		jQuery("option[value='sku']").each(function() {
			if (jQuery(this).attr("selected") == true || jQuery(this).attr("selected") == 'selected') {
				has_sku = has_sku +1;
			}
		});	
		if (has_sku === 0) {
			validates = false;
			jQuery("#todo-list").append("<li>One column must contain an SKU : The SKU must be a unique identifier for each row in the CSV file.</li>");
		} else if (has_sku > 1) {
			validates = false;
			jQuery("#todo-list").append("<li>Your column map contains multiple SKU columns</li>");		
		}
		*/
		var has_name = 0;
		jQuery("option[value='name']").each(function() {
			if (jQuery(this).attr("selected") == true || jQuery(this).attr("selected") == 'selected') {
				has_name = has_name + 1;
			}
		});	     
		
		if (has_name === 0) {
			validates = false;
			jQuery("#todo-list").append("<li>One column must contain a product name</li>");
		} else if (has_name > 1) {
			validates = false;
			jQuery("#todo-list").append("<li>Your column map contains multiple product name columns</li>");		
		}
		var has_price = 0;
		jQuery("option[value='price']").each(function() {  
			if (jQuery(this).attr("selected") == true || jQuery(this).attr("selected") == 'selected') {
				has_price = has_price + 1;
			}
		});	
		if (has_price === 0) {
			validates = false;
			jQuery("#todo-list").append("<li>One column must contain a price</li>");
		} else if (has_price > 1) {
			validates = false;
			jQuery("#todo-list").append("<li>Your column map contains multiple price columns</li>");				
		}
		
		var valid_variation_names = true;
		var no_variation_names = false;
		jQuery(".catskin_variation_label_editor").each(function() {
			if (jQuery(this).is(':visible')) {
				var the_name = jQuery(this).val();
				if (the_name.indexOf(' ') > -1) {
					valid_variation_names = false;
				}
				if (the_name.length === 0) {
					no_variation_names = true;
				}
			}
		});
		
		if (!valid_variation_names) {
			validates = false;
			jQuery("#todo-list").append("<li>Variation Names Must be Alpha Numeric without spaces! (Applies to Shopp Product Importer, not Shopp Itself). </li>");
		}		
		
		if (no_variation_names) {
			validates = false;
			jQuery("#todo-list").append("<li>Variations must be named using Alpha Numeric characters without spaces! (Colour, Size, Print, Condition). </li>");
		}  
		
		var no_custom_tax_selected = false;          
		
	   	jQuery("option[value='custom_tax']").each(function() {
			if (jQuery(this).attr("selected") == true || jQuery(this).attr("selected") == 'selected') {   
				if(jQuery(this).parent().parent().find('.select-tax select').val() == ''){
					 no_custom_tax_selected = true;
				}
			}
		}); 
		
		
		if (no_custom_tax_selected) {
			validates = false;
			jQuery("#todo-list").append("<li>Select a custom taxonomy type.</li>");
		}	
					
		
		var has_category = false;
		jQuery("option[value='category']").each(function() {
			if (jQuery(this).attr("selected") == true || jQuery(this).attr("selected") == 'selected') {
				has_category = true;
			}
		});	
				
		if (!has_category) {
			validates = false;
			jQuery("#todo-list").append("<li>At least one column must contain a category. You can have as many category columns as required. Sub-categories can be specified using forward slash (eg. /Main Category/Sub Category/Sub Sub Category). The importer will create the parent nodes of the Category tree for you. </li>");
		}						
			
		if (validates) {
			jQuery("#todo-list-container").hide(300);
		} else {
			jQuery("#todo-list-container").show(300);
		}
		return validates;
	}
	
	function perform_checks() {
		var is_importing_now = "<?php echo print_r(isset($importing_now)?"YES":"NO",true); ?>";
		if (is_importing_now == "NO") {
			does_val = validate();
			if (does_val === true) {
				ready_for_import();
			} else {
				update_required();
			}
		}
	}

	function update_required() {
			jQuery('.needs-update').show(100);
			jQuery(".needs-update > input").attr("value","Update Importer Settings");		
			jQuery(".needs-update > input").css("background","");
			jQuery('.ready').hide(1000);							
	}
	
	function ready_for_import() {	
			jQuery('.needs-update').hide(1000);
			jQuery('.ready').show(100);				
	}				

	(function($){	
		
		$('.field-type-selector').each(function() {    
		  	if ($(this).val() == 'custom_tax')
			{
				$(this).parent().find('.select-tax').show();
			}else{
				$(this).parent().find('.select-tax').hide();   
			}   
		});    
		
		$('.select-tax select').change(function() {    
			perform_checks();
		});
		
		$('.field-type-selector').each(function() {
			if ($(this).attr("value").indexOf('addon') != -1 || $(this).attr("value").indexOf('variation') != -1 || $(this).attr("value") == 'spec') {
				if($(this).attr("value").indexOf('addon') != -1 || $(this).attr("value").indexOf('variation') != -1) $(this).next().show().next().show().next().show().next().show();
				$(this).next().next().next().show().next().show();
			}
		});					
		$('.field-type-selector').change(function() {
			if ($(this).attr("value").indexOf('addon') != -1 || $(this).attr("value").indexOf('variation') != -1 || $(this).attr("value") == 'spec') {
				if($(this).attr("value").indexOf('addon') != -1 || $(this).attr("value").indexOf('variation') != -1) $(this).next().show().next().show().next().show().next().show();  
				$(this).next().next().next().show(400).next().show(400);
				
			} else {
				$(this).next().hide(100).attr("value","").next().hide(100); 
				$(this).next().next().hide(100).attr("value","").next().hide(100);
			}
			
			if ($(this).val() == 'custom_tax')
			{
				$(this).parent().find('.select-tax').show();
			}else{
				$(this).parent().find('.select-tax').hide();   
			}
		});
		perform_checks();	
	})(jQuery);
</script>
