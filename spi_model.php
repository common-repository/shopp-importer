<?php 
/**
  	Copyright: Copyright © 2010 Catskin Studio
	Licence: see index.php for full licence details
 */
?>
<?php
require_once('spi_files.php');
require_once('spi_images.php');
class spi_model {

	//Initialize Externals
	var $Shopp;
	var $spi;
	
	//Initialize Workers
	var $map 		= array();
	var $variations = array();
	var $products 	= array();
	var $categories = array();
	var $mappedVars = array();   
	
	//Initialize Product
	var $global_variation_counter = 0;     
	var $global_spec_counter = 1;     
	var $global_spec_total = 0;
	
	//Initialize Categories
	var $cat_index = 0;
	
	function spi_model($spi) {	
		$this->spi = $spi;
		$this->Shopp = $spi->Shopp;
		//Is this used?
		$this->cat_index = $this->get_next_shopp_category_id();  
		$this->files = new spi_files($this->spi);  
	}
	
	// !bookmark : Execution Functions
	function execute() {
		//The link between CSV and Known Shopp Fields... 
		//Map them out so we can work with them
		$this->initialize_map();
		
		//get_next_product selects the next product from the shopp_product_importer data table
		//which meets the status code criteria.
		
		//get_next_set selects all products in the table with the id returned by get_next_product
		
		//process_set updates the status for the rows we've just used so we don't reuse them.
		
		//0 - Initialize Variations  
		
		$this->variations = array();
		while ($p_row = $this->get_next_product(0)) {
				$p_set = $this->get_next_set($p_row->spi_id);
				$this->initialize_variations($p_row->spi_id);
				$this->process_set($p_row->spi_id,1);
		}
		//1 - Populate Variations
		while ($p_row = $this->get_next_product(1)) {
				$p_set = $this->get_next_set($p_row->spi_id);
				$this->populate_variations($p_set);
				$this->process_set($p_row->spi_id,2);
		}
		
  

			
		//2 - Initialize Categories
		$this->categories = array();
		while ($p_row = $this->get_next_product(2)) {
				$p_set = $this->get_next_set($p_row->spi_id);
				$this->initialize_categories($p_row->spi_id);
				$this->process_set($p_row->spi_id,3);
		}
			
		//3 - Initialize Products
		$base_id = $this->get_next_shopp_product_id();
		while ($p_row = $this->get_next_product(3)) {
			$p_set = $this->get_next_set($p_row->spi_id); 
			
			$this->global_variation_counter = 0;
			//Does the product already exist in shopp? 
			$existing_product = $this->product_exists($p_row->spi_name,$p_row->spi_sku);
			if ($existing_product) {
				$this->products[$existing_product] = new map_product();
				$this->initialize_product($this->products[$existing_product],$p_row->spi_id,$existing_product);
				$this->process_set($p_row->spi_id,10,$existing_product);
			} else {
				$this->products[$base_id] = new map_product();
				$this->initialize_product($this->products[$base_id],$p_row->spi_id,$base_id);
				$this->process_set($p_row->spi_id,10,$base_id);
				$base_id++;
			}
		}		
		//10 - Initialize Prices
		/*
		foreach ($this->products as $map_product) {
			$this->initialize_prices($map_product);
		} 
		*/
		$this->process_all(20);
		return $this->products;
	}   
	
	function execute_images()
	{
		$csv_product = $this->get_product($_POST['mapping']['csv_id']);
		
		
		$image_format = $this->Shopp->Settings->get('catskin_importer_imageformat');
		$output = "";  
		foreach($csv_product as $key =>$value){
			
			
			if(strpos($key,'spi_image') !== false){
				$image_url = str_replace('{val}',$value,$image_format); 
				$image_id = shopp_add_product_image($_POST['mapping']['shopp_id'],$image_url);
				$shopp_product = shopp_product($_POST['mapping']['shopp_id']);
	  
				$output .= "<div style='float:left; display:inline; width:110px; height:130px; border:1px solid #CCC; background:#FFF; margin:3px; padding:5px; margin-top:15px;'><p style='text-align:center;'>sku: ".$_POST['mapping']['csv_id']."</p><img src='?siid=".$image_id."&".$shopp_product->images[$image_id]->resizing(130,110,1)."' style='max-width:100px; max-height:90px;'/></div>"; 
			}			
		}
		
		echo $output;
		
	}    
	
	function get_product($product_id) {
		global $wpdb;
		$query = "SELECT * FROM {$wpdb->prefix}shopp_importer WHERE (product_id = {$product_id}) ORDER BY id limit 1";
		return $wpdb->get_row($query,ARRAY_A);
	}
	
	function execute_images_old() {		
		
		$this->initialize_map();
	
		//Populate Images
		$cnt = 0;
		$output = "";
		$images = array();
		//Debugging Any Images Exist... Check console or remove debug line from function any_images_exist
		if ($this->any_images_exist() > 0) {
			if ($p_row = $this->get_next_product(20)) {
				$p_set = $this->get_next_product(20,true);
				foreach ($p_set as $pmap) {	
					foreach ($this->map as $mset) {
						switch ($mset['type']) {
							case 'image':    
								$value = $this->Shopp->Settings->get('catskin_importer_imageformat');        
								$value = str_replace('{val}',$this->get_row_mapped_var($pmap->id,$mset['header']),$value);  	 
						 
								
								if (strlen($value) > 0) {
									$cnt = $cnt + $this->_populate_image($p_set,$pmap,$mset);
									$output .= "<div style='float:left; display:inline; width:110px; height:130px; border:1px solid #CCC; background:#FFF; margin:3px; padding:5px; margin-top:15px;'><p style='text-align:center;'>sku: ".$this->get_row_mapped_var($p_row->id,"spi_sku")."</p><img src='".$value."' style='max-width:100px; max-height:90px;'/></div>";
								}
								break;
						}
					}
				}	
				$this->process_product($p_row->id,50);	
				return $output;
						
			} else {
				//$this->process_product($p_row->spi_id,50);	
				return "no-more";
			}
		} else {		
			return "no-images";
		}
	}	
	                           
	// ==========
	// = function created by tim.vde =
	// ==========     
	public function execute_mega_query()
	{               
		$imported_products = array('products'=>array());
		$next_category_id = $this->get_next_shopp_category_id();
		$used_categories = array();
		$has_image = false;
		$shopp_cats = shopp_subcategories();  
	    foreach ($this->products as $map_product) {    
		
			if (strlen($map_product->description) > 0) {
				$description = $spi_files->load_html_from_file($map_product->description); 
			} else { 
				$description = 	$map_product->description_text;
			}
		       
			$is_published = trim(strtolower($map_product->published,false)) == 'off' ? false : true;
			if($is_published){
				$publish = array('flag' => true);
			} 
			      
			$is_featured = trim(strtolower($this->defval($map_product->featured,false))) == 'on' ? true : false;
	        
			$is_taxed = trim(strtolower($map_product->tax)) == 'off' ? false : true;  
			 
			//categories			
			$categories = array('terms' => array()); 
			
			
			

			
			foreach ($this->categories as $category) {

				if (in_array($map_product->csv_id,$category->csv_product_ids,true)) {
					$categoryId = false;

				    foreach($shopp_cats as $shopp_cat){
						if($shopp_cat->name == $category->value){
						   $categoryId = $shopp_cat->term_id; 
						}
					}    

					if(!$categoryId){
						$categoryId = shopp_add_product_category($category->value);
						$shopp_cats[$categoryId] = shopp_product_category($categoryId);
					}
					$categories['terms'][] = $categoryId;  
				}
				 	
			}
			  
			//tags
		    $product_tags = array();
		    foreach($map_product->tags as $tagobject)
			{
				$tag_split = explode(CATEGORY_TAGS_DELIMITER,$tagobject->value);
			   	$product_tags = array_merge($tag_split,$product_tags);
			}   
			
			$tagIds = array();
	   	    if(count($product_tags)){  	  
		   	 	foreach ($product_tags as $tag) {  
					$tagId = shopp_product_tag($tag);
					if(!$tagId){
						$tagId = shopp_add_product_tag($tag);
					}  
					$tagIds[] = $tagId;  
				}
			}
			$tags = array('terms' => $tagIds);  
			
			//terms
			$terms = array(); 			
			if($map_product->custom_tax && count($map_product->custom_tax)){
				foreach($map_product->custom_tax as $tax){ 
				   
					$termValues = explode(CATEGORY_TAGS_DELIMITER,$tax['value']);
					foreach($termValues as $termValue)
					{    
						$termId = shopp_product_term($termValue); 
					   	if(!$termId){
							$termId = shopp_add_product_term($termValue,$tax['taxonomy']);
						}
						$terms[] = array('id'=>$termId,'taxonomy'=>$tax['taxonomy'],'value'=>$termValue); 
					}
					
					
				} 
			} 

			
			//specs
			foreach($map_product->specs as $spec) {
				if(!$specs) $specs = array();   
				$specs[$spec->name] = $spec->value;
			}   
			
			//variations
			$variants = array('menu'=>array());   
			$variation_count = 0;
			foreach ($map_product->variations as $variation_group_key => $variation_group_values) {
				if(!array_key_exists($variation_group_key,$variants['menu'])){
				  $variants['menu'][$variation_group_key] = array();     
				} 
				foreach ($variation_group_values as $variation) {
					$variant = array('type' => 'Shipped','taxed'=> true);
					$variant['option'] = array($variation_group_key => $variation->name); 
					foreach ($variation->values as $variation_value_key => $variation_value_value) {
						if($variation_value_key == 'price'){
							$variation_value_value = floatval($variation_value_value);
						}
						
						if(trim(strtolower($variation_value_value)) == 'off' || trim(strtolower($variation_value_value)) == 'on'){							
							$variation_value_value = (trim(strtolower($variation_value_value)) == 'off') ? false : true;
						}
						//echo $variation_value_key.'='.$variation_value_value.'<br/>';
						$variant[$variation_value_key] = $variation_value_value;
						$variation_count++;
					}                      
					
				   $variants['menu'][$variation_group_key][] = $variation->name;
				   $variants[$variation_count] = $variant;
				}   
			}
			 
			//addons
			$addons = array('menu'=>array());   
			$addon_count = 0;
			foreach ($map_product->addons as $variation_group_key => $variation_group_values) {
				if(!array_key_exists($variation_group_key,$addons['menu'])){
				  $addons['menu'][$variation_group_key] = array();     
				} 
				foreach ($variation_group_values as $variation) {
					$variant = array('type' => 'Shipped','taxed'=> true);
					$variant['option'] = array($variation_group_key => $variation->name); 
					foreach ($variation->values as $variation_value_key => $variation_value_value) {
						if($variation_value_key == 'price'){
							$variation_value_value = floatval($variation_value_value);
						}
						
						if(trim(strtolower($variation_value_value)) == 'off' || trim(strtolower($variation_value_value)) == 'on'){							
							$variation_value_value = (trim(strtolower($variation_value_value)) == 'off') ? false : true;
						}     
						//echo $variation_value_key.'='.$variation_value_value.'<br/>'; 
						$variant[$variation_value_key] = $variation_value_value;
						$addon_count++;
					}                      
					
				   $addons['menu'][$variation_group_key][] = $variation->name;
				   $addons[$addon_count] = $variant;
				}   
			} 
			
			//shipping
			$shipping = array(
			    'flag' => (bool) $map_product->shipping
			);  
			
			if($map_product->ship_fee){
				$shipping['fee'] = (float) $map_product->ship_fee; 
			}  
			
			if($map_product->weight){
				$shipping['weight'] = (float) $map_product->weight; 
			}
		 
			$product_data = array();    
			$product_data['name'] = $map_product->name;  
			$product_data['slug'] = $this->defval($map_product->slug,sanitize_title($map_product->name));
			if(isset($map_product->summary)) $product_data['summary'] = $map_product->summary;
			$product_data['featured'] = $is_featured;           
			if(isset($description)) 			$product_data['description'] = $description;
			if(isset($publish)) 				$product_data['publish'] = $publish;  
			if(isset($specs)) 					$product_data['specs'] = $specs;  
		    if(isset($categories['terms'])) 	$product_data['categories'] = $categories;    
		    if(isset($tags['terms']))  		    $product_data['tags'] = $tags;    
		    if(count($variants['menu'])) 		$product_data['variants'] = $variants; 
		    if(count($addons['menu'])) 			$product_data['addons'] = $addons;   
		    if(count($product_tags)) 			$product_data['tags'] = array('terms'=>$product_tags);    
		       
			
			
			
			
			
		
		
		 
			
			
		
			$available_price_types = array('Shipped', 'Virtual', 'Download', 'Donation', 'Subscription', 'N/A');
		
			if($variation_count == 0){
			    $price_type = 'Shipped';    
			    if($map_product->price_type && in_array(trim(ucfirst($map_product->price_type)), $available_price_types)) $price_type = $map_product->price_type;
				
				$product_data['single'] = array('price'=>(float) $map_product->price,'type' => trim(ucfirst($price_type)),'taxed'=> $is_taxed);
			    
				if($map_product->sale_price && $map_product->sale){
					$product_data['single']['sale'] = array(
					    'flag' => (boolean) trim(strtolower($map_product->sale)) == 'off' ? false : true,   						
					    'price' => (float) $map_product->sale_price
					);
				}
				if($map_product->sku && $map_product->stock){
					$product_data['single']['inventory'] = array(
					    'flag' => true,
					    'stock' => (int) $map_product->stock, 
					    'sku' => $map_product->sku
					);
				}  
				
				$product_data['single']['shipping'] = $shipping;   
				 		
			}     
			      
			
			foreach ($map_product->images as $imageObject) {
				if(intval($imageObject->value) > 0){ 
					$has_image = true;
				}
			} 
			
			
		   
		
			
			
	        
			if(SHOPP_IMPORTER_DEBUG){
				echo "<pre>";
				var_dump(($product_data));       //
				//var_dump(_validate_product_data($product_data));       //
				echo "</pre>";        
			}
		 
		    $product = shopp_add_product($product_data);  
		
		    if(count($terms)){
				foreach($terms as $term)
				{    
					shopp_product_add_terms($product->id,array($term['id']),$term['taxonomy']);
				}
			}
			     
		   	$imported = array('csv_id'=>$map_product->id,'shopp_id'=>$product->id);
		  	$imported_products['products'][] = $imported;      
		    if(SHOPP_IMPORTER_DEBUG){  
			   print_r($imported); 
			}
			//$map_product = null;
			$product = null;
			$product_data = null;     
			
			  
		}
		$imported_products['count'] = count($imported_products['products']);
        $imported_products['has_image'] = $has_image;          
		echo json_encode($imported_products);
	
		
	}
 
	function index_content() {
	
	
	}	
	
	// !bookmark : (End) Execution Functions	
	// !bookmark : Processing Fuctions	

	function initialize_map() {
		//Load the map from shopp's Settings table. Saved there by column mapping in 
		//importer settings page and apply it to an array that we can use to understand the 
		//data being pulled in.
		$map = $this->Shopp->Settings->get('catskin_importer_column_map');
		//initialize counters
		$column = 0;
		$variation = 0;
		$category = 0;
		$tag = 0;
		$image = 0;   
		$spec =0;
		//Using $map array create a global field map based on the currently active CSV 
		foreach ($map as $item) {
			//does the map item have a special power? 
			//Special power columns arent exclusive so we need to count 
			//how many of each special powers we have. 
			//$hidx holds the index conter for that special power
			switch ($item['type']) {
				case 'variation': $variation++; $hidx = $variation; break;  
				case 'spec': $spec++; $hidx = $spec; break;
				case 'category': $category++; $hidx = $category; break;
				case 'tag': $tag++; $hidx = $tag; break;
				case 'image': $image++; $hidx = $image; break;	
				default: $hidx = '';
			}
			
			//We handle variations by name for labeling purposes so instead of getting an index 
			//it's given a name
			if ($item['type'] == 'variation' || $item['type'] == 'spec') 
				$column_header = 'spi_'.$item['label']; 
			else
				$column_header = 'spi_'.$item['type'].$hidx;
			 
			     
		 	if($item['type'] == 'custom_tax'){				
				$column_header = 'spi_custom_tax-'.$item['custom_tax'];
			}   
			$map = array('type'=>$item['type'],'group'=>$item['group'],'label'=>$item['label'],'header'=>$column_header,'idx'=>$hidx);
		    if($item['type'] == 'custom_tax'){	  
				$map['custom_tax'] = $item['custom_tax'];   
			}
			//'<pre>'.print_r($item).'</pre>';
			$this->map[] = $map;
		}
		$this->global_spec_total = $specs;
	}	
	
	function initialize_variations($csv_product_id) {
		foreach ($this->map as $mset) { 
			if(strpos($mset["type"],'variation') !== false){ 
				if ($this->any_exist($mset['header'],$csv_product_id) > 0) {
					$map_variation = new map_variation();
					$map_variation->name =  $mset['header'];
					$map_variation->csv_product_id = $csv_product_id;
					$map_variation->values = array();
					if (array_search($map_variation,$this->variations) === false) {
						$this->variations[] = $map_variation; 
					}
				} 		
			}  
		}				
	}	
	
	function populate_variations($product_set) {
		foreach ($product_set as $pmap) {	
			foreach ($this->map as $mset) {
				if(strpos($mset["type"],'variation') !== false){
				   	$variation_value = new map_variation_value();
					eval('$variation_value->value = $pmap->'.$mset['header'].';');
					if ($this->find_variation($mset['header'],$pmap->spi_id) > -1 && 
						$this->find_variation_value(
							$this->variations[$this->find_variation($mset['header'],$pmap->spi_id)]->values,
								$variation_value->value) == -1)
									$this->variations[$this->find_variation($mset['header'],$pmap->spi_id)]->values[] = $variation_value;   					
					   
				}
			}
		}	
	}	 

	
	
	function initialize_categories($csv_product_id) {
		$cat_index = $this->cat_index;
		$parent_index = 0;
		foreach ($this->map as $mset) {
			if($mset['type'] == 'category'){
				   if ($this->any_exist($mset['header'],$csv_product_id) > 0) {
					//initialize our arrays for reuse
					$uri_array = array();
					//cat_string = the raw slash delimited category data
					$cat_string = $this->get_mapped_var($csv_product_id,$mset['header']);					
					$cat_array = explode(CATEGORY_TAGS_DELIMITER,$cat_string);
					//reverse the array for ease of use
					array_reverse($cat_array);
					for ($i=0; $i<sizeof($cat_array);$i++) {
						//build an array of category uri's we're going to use these as the 
						//unique identifier for categories
						$uri_array[$i] = sanitize_title_with_dashes($cat_array[$i]);
					}			
					for ($i=0; $i<sizeof($cat_array);$i++) {
						$map_category = new map_category();
						$map_category->name =  $mset['header'];
						$map_category->value = $cat_array[$i];
						$map_category->slug = sanitize_title_with_dashes($cat_array[$i]);
						$map_category->id = $cat_index;
						$map_category->parent_id = $parent_index;
						$map_category->csv_product_ids[] = $csv_product_id;
						$pop_array = $uri_array;							
						for ($j=0;$j<(sizeof($cat_array)-($i+1)); $j++ ) {
							array_pop($pop_array);
						}
						$parent_pop_array = $uri_array;
						for ($j=0;$j<(sizeof($cat_array)-($i)); $j++ ) {
							array_pop($parent_pop_array);
						}							
						if (sizeof($pop_array) == 1) {
							$map_category->parent_id = 0; 
						} else {
							$map_category->parent_id = $parent_index; 
						}
						$map_category->uri = join(CATEGORY_TAGS_DELIMITER,$pop_array);
						$map_category->parent_uri = join(CATEGORY_TAGS_DELIMITER,$parent_pop_array);
						$existing_shopp_category = $this->category_exists($map_category->uri);
						if (!is_null($this->category_by_uri($map_category->uri))) {
							$this->categories[$this->key_to_category_by_uri($map_category->uri)]->csv_product_ids[] = $csv_product_id;
						} else {
							if (is_null($this->category_by_uri($map_category->parent_uri))) {
								$map_category->parent_id = 0;
							} else {
								$parent_category = $this->category_by_uri($map_category->parent_uri);
								$map_category->parent_id = $parent_category->id;
							}
							if ($existing_shopp_category) {
								$map_category->id = $existing_shopp_category->id;
								$map_category->parent_id = $existing_shopp_category->parent;
							} else {
								$cat_index++;
							}
							$this->categories[] = $map_category;							
						}
					}
				} 		
			}
		}	
		$this->cat_index = $cat_index;	
	}		
	
	function initialize_product(&$map_product,$csv_product_id,$shopp_product_id) {   
		

		$this->global_spec_counter = 1;
		$map_product->id = $shopp_product_id;
		$map_product->csv_id = $csv_product_id;
		$cat_index = $this->cat_index;
		foreach ($this->map as $mset) {
			$parent_index = 0;
			switch ($mset['type']) {
				case 'description':
					$map_product->description = $this->get_mapped_var($csv_product_id,$mset['header']);
					break;					
				case 'descriptiontext':
					$map_product->description_text = $this->get_mapped_var($csv_product_id,$mset['header']);
					break;					
				case 'featured':
					$map_product->featured = $this->get_mapped_var($csv_product_id,$mset['header']);
					break;
				case 'image':
					$map_image = new map_image();
					$map_image->name =  $mset['header'];
					$map_image->value = $this->get_mapped_var($csv_product_id,$mset['header']);
					if (strlen($map_image->value) > 0) $map_product->images[] = $map_image; 
					break;
				case 'inventory':
					$map_product->inventory = $this->get_mapped_var($csv_product_id,$mset['header']);
					break;
				case 'name':
					$map_product->name = $this->get_mapped_var($csv_product_id,$mset['header']);
					break;    			
				case 'price':
					$map_product->price = $this->parse_float($this->get_mapped_var($csv_product_id,$mset['header']));
					break;
				case 'published':
					$map_product->published = $this->get_mapped_var($csv_product_id,$mset['header']);
					break;
				case 'sale':
					$map_product->sale = $this->get_mapped_var($csv_product_id,$mset['header']);
					break;
				case 'saleprice':
					$map_product->sale_price = $this->parse_float($this->get_mapped_var($csv_product_id,$mset['header']));
					break;
				case 'shipfee':
					$map_product->ship_fee = $this->parse_float($this->get_mapped_var($csv_product_id,$mset['header']));
					break;
				case 'sku':
					$map_product->sku = $this->get_mapped_var($csv_product_id,$mset['header']);
					break;
				case 'slug':
					$map_product->slug = $this->get_mapped_var($csv_product_id,$mset['header']);
					break;
				case 'order':
					$map_product->order = $this->get_mapped_var($csv_product_id,$mset['header']);
					break;
				case 'stock':
					$map_product->stock = $this->get_mapped_var($csv_product_id,$mset['header']);
					break;
				case 'summary':
					$map_product->summary = $this->get_mapped_var($csv_product_id,$mset['header']);
					break;
				case 'tag':
					$map_tag = new map_tag();
					$map_tag->name =  $mset['header'];
					$map_tag->value = $this->get_mapped_var($csv_product_id,$mset['header']);
					if (strlen($map_tag->value) > 0) $map_product->tags[] = $map_tag; 
					break;
				case 'tax':
					$map_product->tax = $this->get_mapped_var($csv_product_id,$mset['header']);
					break;
				case 'price_type':
					$map_product->price_type = $this->get_mapped_var($csv_product_id,$mset['header']);
					break; 
				case 'weight':
					$map_product->weight = $this->parse_float($this->get_mapped_var($csv_product_id,$mset['header']));
					break;   
				case 'spec':    
				
				 	    $map_spec = new map_spec();
						$map_spec->name =  substr($mset['header'],4);   					
						$map_spec->value = $this->get_mapped_var($csv_product_id,'spi_spec'.$this->global_spec_counter);
					  //echo 'spi_spec'.$this->global_spec_counter.'<br>';
						$map_product->specs[] = $map_spec;    
						$this->global_spec_counter++;
						   
					//}
					break;
				case 'custom_tax' :
					if(!$map_product->custom_tax){
						$map_product->custom_tax = array();
					} 
					$mset['header'] = str_replace('-','_',$mset['header']);                               	                        
					$map_product->custom_tax[] = array('taxonomy'=>$mset['custom_tax'],'value'=>$this->get_mapped_var($csv_product_id,$mset['header']));
					break;
		   		case 'shipping' :       
					$map_product->shipping = strtolower(trim($this->get_mapped_var($csv_product_id,$mset['header']))) == 'off' ? false : true;
					break;
			}
			
			if(strpos($mset["type"],'variation') !== false){
				if($this->any_exist($mset['header'].$this->global_variation_counter+1,$csv_product_id) > 0) { 
					$variation_key = str_replace('variation_','',$mset["type"]);
					$map_variation = new map_variation();    
					$map_variation->name = $mset['label']; 
					$this->global_variation_counter++;    
					$map_variation->values[$variation_key] = $this->get_mapped_var($csv_product_id,$mset['header'].$this->global_variation_counter);   

					$map_variation->id = $this->global_variation_counter;
					$found = false; 
					foreach($map_product->variations[$mset['group']] as $varia_found)
					{
                             if($varia_found->name == $mset['label']){
	                         	$found = $varia_found;
	 							break;
							 }
					}   
					if($found){
					   $found->values[$variation_key] = $this->get_mapped_var($csv_product_id,$mset['header'].$this->global_variation_counter); 
					}else{
					  $map_product->variations[$mset['group']][] = $map_variation;   
					}      
				}	
			}else if(strpos($mset["type"],'addon') !== false){
				if($this->any_exist($mset['header'].$this->global_addon_counter+1,$csv_product_id) > 0) { 
					$addon_key = str_replace('addon_','',$mset["type"]);
					$map_variation = new map_variation();    
					$map_variation->name = $mset['label']; 
					$this->global_addon_counter++;    
					$map_variation->values[$addon_key] = $this->get_mapped_var($csv_product_id,$mset['header'].$this->global_addon_counter);   

					$map_variation->id = $this->global_addon_counter;
					$found = false; 
					foreach($map_product->addons[$mset['group']] as $varia_found)
					{
                             if($varia_found->name == $mset['label']){
	                         	$found = $varia_found;
	 							break;
							 }
					}   
					if($found){
					   $found->values[$addon_key] = $this->get_mapped_var($csv_product_id,$mset['header'].$this->global_addon_counter); 
					}else{
					  $map_product->addons[$mset['group']][] = $map_variation;   
					}      
				}	
			}    
			
		}
		$this->last_csv_product_id = $csv_product_id; 
		if (!isset($map_product->variations)) { 
			$map_product->has_variations = 'off'; 
		} else {
			if (!is_array($map_product->variations)) {
				$map_product->has_variations = 'off'; 
			} else {
				if (count($map_product->variations) == 0) {
					$map_product->has_variations = 'off'; 
				} else {
					$map_product->has_variations = 'on'; 
				}
			}
		} 
		$map_product->options = $this->determine_product_options($map_product,$csv_product_id);
	}
	
	function initialize_prices($map_product) {
		if (count(unserialize($map_product->options)) > 0) {
			$combinations = array();
			$product_options = unserialize($map_product->options);
			foreach ($product_options as $option_group) {
				$sets = false;
				foreach ($option_group['options'] as $options) {
					$sets[]= $options['id'];
				}
				$groups[] = $sets;
			}		
			$this->_get_combos($groups,$combinations);
		}
		unset($row_data);
		$row_data = $this->get_importer_data($map_product);
		$row_type = (isset($groups))?"N/A":$this->defval($row_data->spi_type,"Shipped");
		$row_price = (isset($groups))?"0.00":$this->defval($row_data->spi_price,"0.00");


		$tc1 = array(
			"product"=>$map_product->id,
			"options"=>"",
			"optionkey"=>"0",
			"label"=>"Price & Delivery",
			"context"=>"product",
			"type"=>$row_type,
			"sku"=>(isset($groups))?"":$this->defval($row_data->spi_sku,""),
			"price"=>$this->parse_float($row_price),
			"saleprice"=>$this->parse_float((isset($groups))?"0.00":$this->defval($row_data->spi_saleprice,"0.00")),
			"weight"=>$this->parse_float((isset($groups))?"0.000":$this->defval($row_data->spi_weight,"0.000")),
			"shipfee"=>$this->parse_float((isset($groups))?"0.00":$this->defval($row_data->spi_shipfee,"0.00")),
			"stock"=>(isset($groups))?"0":$this->defval($row_data->spi_stock,"0"),
			"inventory"=>(isset($groups))?"off":$this->defval($row_data->spi_inventory,"off"),
			"sale"=>(isset($groups))?"off":$this->defval($row_data->spi_sale,"off"),
			"shipping"=>(isset($groups))?"on":$this->defval($row_data->spi_shipping,"on"),
			"tax"=>(isset($groups))?"on":$this->defval($row_data->spi_tax,"on"),
			"donation"=>$this->defval($row_data->spi_donation,'a:2:{s:3:"var";s:3:"off";s:3:"min";s:3:"off";}'),
			"sortorder"=>(isset($groups))?"0":$this->defval($row_data->spi_order,"0")
		);
		$this->products[$map_product->id]->prices[] = $tc1;	
		if (isset($combinations)) {			
			foreach ($combinations as $combo) {
				unset($row_data);     
				$row_data = $this->get_option_optionkey_data($map_product,implode(',',$combo));		
				$row_type = $this->defval($row_data->spi_type,"Shipped");
				$row_price = $this->defval($row_data->spi_price,"0.00");
				if ($row_price == "0.00" || $row_price == "0" || strlen($row_price) == 0){
					$row_type = "N/A";	
					$row_price = "";
				}			
				
				$tc1 = array(
					"product"=>$map_product->id,
					"options"=>implode(',',$combo),
					"optionkey"=>$this->get_option_optionkey($map_product,$combo),
					"label"=>$this->get_option_label($map_product,$combo),
					"context"=>"variation",
					"type"=>$row_type,
					"sku"=>$this->defval($row_data->spi_sku,""),
					"price"=>$this->parse_float($row_price),
					"saleprice"=>$this->parse_float($this->defval($row_data->spi_saleprice,"0.00")),
					"weight"=>$this->parse_float($this->defval($row_data->spi_weight,"0.000")),
					"shipfee"=>$this->parse_float($this->defval($row_data->spi_shipfee,"0.00")),
					"stock"=>$this->defval($row_data->spi_stock,"0"),
					"inventory"=>$this->defval($row_data->spi_inventory,"off"),
					"sale"=>$this->defval($row_data->spi_sale,"off"),
					"shipping"=>$this->defval($row_data->spi_shipping,"on"),
					"tax"=>$this->defval($row_data->spi_tax,"on"),
					"donation"=>$this->defval($row_data->spi_donation,'a:2:{s:3:"var";s:3:"off";s:3:"min";s:3:"off";}'),
					"sortorder"=>$this->defval($row_data->spi_order,"0")					
				);
				$this->products[$map_product->id]->prices[] = $tc1;
			}
		}
		
	}
	
	// !bookmark : (End) Processing Functions	
	
	//Checks to see if a specific type of field exists in the shopp_product_importer data table
	//csv_product_id relates to $this->map[$id]['header'] eg. spi_saleprice, spi_tag1, spi_name
	//returns a count of those existing.
	function any_exist($header,$csv_product_id) {
		global $wpdb;
			$query = "SELECT COUNT(NULLIF(TRIM({$header}), '')) FROM {$wpdb->prefix}shopp_importer WHERE spi_id = '{$csv_product_id}';";
			$result = $wpdb->get_var($query);		
		return $result;
	}
	
	function any_images_exist() {
		global $wpdb;
		$result = 0;
		foreach ($this->map as $mset) {
			switch ($mset['type']) {
				case 'image':
					$query = "SELECT COUNT(NULLIF(TRIM({$mset['header']}), '')) FROM {$wpdb->prefix}shopp_importer;";
					$result = $result + $wpdb->get_var($query);
					break;
			}
		}	
		return $result;
	}	
	
	function category_by_uri($uri) {
		foreach ($this->categories as $category) {
			if ($category->uri == $uri) return $category;
		}
		return null;
	}	
	
	function category_exists($uri) {
		global $wpdb;
			$query = "SELECT * FROM {$wpdb->prefix}shopp_category WHERE uri = '{$uri}';";
			$result = $wpdb->get_row($query);		
		return $result;
	}	
	
	function defval($value,$default) {
		return strlen($value)>0?$value:$default;
	}
		
	function determine_product_options($map_product,$csv_product_id) {
		$options = array();
		$options_index = 1;
		$option_value_uid = 1;
		foreach ($this->variations as $variation) {
			if ($variation->csv_product_id == $csv_product_id) {
				$option_values = array();
				$option_value_index = 0;
				foreach ($variation->values as $val) {
					$option_values[$option_value_index] = array(
						"id"=>(string)$option_value_uid,
						"name"=>$val->value,
						"linked"=>"off"
					);
					$option_value_uid++;
					$option_value_index++;
				}
				$options[$options_index] = 
					array(
						"id"=>(string)$options_index,
						"name"=>ltrim($variation->name,"spi_"),
						"options"=>$option_values);
				$options_index++;
			}
		}
		return serialize($options);
	}	
	
	function find_variation($name, $csv_product_id) {
		foreach ($this->variations as $index=>$var) {
			if ($var->name == $name && $var->csv_product_id == $csv_product_id) {
				return $index;
			}
		}	
		return -1;
	}	
	
	function find_variation_value($valuearray,$value) {
		foreach ($valuearray as $index=>$var) {
			if ($var->value == $value) {
				return $index;
			}
		}	
		return -1;
	}		

	private function _get_combos(&$lists,&$result,$stack=array(),$pos=0)
	{
		$list = $lists[$pos];
	 	if(is_array($list)) {
	  		foreach($list as $word) {
	   			array_push($stack,$word);
	   			if(count($lists)==count($stack)) {
	   				$result[]=$stack;
	   			} else {
	   				if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
	   					$this->_get_combos($lists,$result,$stack,$pos+1);
	   				} else {
	   					$this->_get_combos(&$lists,&$result,$stack,$pos+1);
	   				}
	   			}			 
	   			array_pop($stack);
	  		}
	 	}
	}	
	
	function get_importer_data($map_product,$combostring = '') {
		global $wpdb;
		
		$empty_result = (object)null;
		$empty_result->spi_type = null;
		$empty_result->spi_price = null;
		$empty_result->spi_sku = null;
		$empty_result->spi_saleprice = null;
		$empty_result->spi_weight = null;
		$empty_result->spi_shipfee = null;
		$empty_result->spi_stock = null;
		$empty_result->spi_inventory = null;
		$empty_result->spi_sale = null;
		$empty_result->spi_shipping = null;
		$empty_result->spi_tax = null;
		$empty_result->spi_donation = null;
		$empty_result->spi_order = null;

		if (strlen($combostring) > 0) {
			$combo = explode(",",$combostring);
			$combo_index = 0;
			$string = "";
			foreach ($map_product->variations as $variation) {
				$option_id_label = $this->get_optionid_label($map_product,$combo[$combo_index]);
				if ($combo_index > 0) $and = " AND "; else $and = "";
				$string .= "{$and} {$variation->name} = '{$option_id_label}' ";
				$combo_index++;
			}		
			$query = "SELECT * FROM {$wpdb->prefix}shopp_importer WHERE {$string} AND spi_id = '{$map_product->csv_id}'";
		} else {
			$query = "SELECT * FROM {$wpdb->prefix}shopp_importer WHERE spi_id = '{$map_product->csv_id}'";
		}
		$result = $wpdb->get_row($query);
		$merged_result = (object) array_merge((array) $empty_result, (array) $result);
		return $merged_result;
	}	
	
	function get_mapped_var($id,$column_header) {
		global $wpdb;
		
		if(!isset($this->mappedVars[$id])){
     		$query = "SELECT * FROM {$wpdb->prefix}shopp_importer WHERE (spi_id = '{$id}') ORDER BY id limit 1";
			$this->mappedVars[$id] = $wpdb->get_row($query);
		}
		$result = $this->mappedVars[$id]->{$column_header};
		
		return $result;	
	}	
	
	//get_next_product selects the next product from the shopp_product_importer data table
	//which meets the status code criteria.	
	function get_next_product($status,$as_set=false) {
		global $wpdb;
		$query = "SELECT * FROM {$wpdb->prefix}shopp_importer WHERE (processing_status = {$status}) ORDER BY id limit 1";
		if ($as_set) $result = $wpdb->get_results($query,OBJECT);
		else $result = $wpdb->get_row($query,OBJECT);
		return $result;
	}
	
	//get_next_set selects all products in the table with the id returned by get_next_product	
	function get_next_set($id) {
		global $wpdb;
		$id = trim($id);
		$query = "SELECT * FROM {$wpdb->prefix}shopp_importer WHERE spi_id = '{$id}' ORDER BY id ";
		$result = $wpdb->get_results($query,OBJECT);
		return $result;
	}	
	
	function get_next_shopp_product_id() {
		global $wpdb;
		$query = "SELECT id FROM {$wpdb->prefix}shopp_product ORDER BY id DESC limit 1";
		$result = $wpdb->get_var($query);
		if (!is_numeric($result)) $result = 1; else $result++;
		return $result;		
	}
	
	function get_next_shopp_tag_id() {
		global $wpdb;
		$query = "SELECT id FROM {$wpdb->prefix}shopp_tag ORDER BY id DESC limit 1";
		$result = $wpdb->get_var($query);
		if (!is_numeric($result)) $result = 1; else $result++;
		return $result;		
	}	
	
	function get_next_shopp_category_id() {
		global $wpdb;
		$query = "SELECT id FROM {$wpdb->prefix}shopp_category ORDER BY id DESC limit 1";
		$result = $wpdb->get_var($query);
		if (!is_numeric($result)) $result = 1; else $result++;
		return $result;		
	}		
	
	function get_option_label($map_product,$combo) {
		
		if (is_array(unserialize($map_product->options))) {
			$product_options = unserialize($map_product->options);
			$lbl_index = 0;
			$label = "";
			foreach ($product_options as $gkey=>$option_group) {
				foreach($option_group['options'] as $okey=>$option) {
					foreach ($combo as $check_value) {
						if ($option['id'] == $check_value) {
							if ($lbl_index > 0) $seperator = ', '; else $seperator = '';
							$label .= $seperator.$option['name'];
							$lbl_index++;
						}
					}
				}
			}	
		}
		return $label;			
	}	
	
	function get_option_optionkey($map_product,$ids,$deprecated = false) {
		if ($deprecated) $factor = 101;
		else $factor = 7001;
		if (empty($ids)) return 0;
		$key = null;
		foreach ($ids as $set => $id) 
			$key = $key ^ ($id*$factor);
		return $key;			
	}				
	
	function get_optionid_label($map_product, $check_value) {
		if (is_array(unserialize($map_product->options))) {
			$product_options = unserialize($map_product->options);
			foreach ($product_options as $gkey=>$option_group) {
				foreach($option_group['options'] as $okey=>$option) {
					if ($option['id'] == $check_value) {
						return $option['name'];
					}
				}
			}	
		}
	}	
	
	function get_row_mapped_var($id,$column_header) {
		global $wpdb;
		$query = "SELECT {$column_header} FROM {$wpdb->prefix}shopp_importer WHERE (id = '{$id}') ORDER BY id limit 1";
		$result = $wpdb->get_var($query);
		return $result;	
	}		
	
	function key_to_category_by_uri($uri) {
		foreach ($this->categories as $key=>$category) {
			if ($category->uri == $uri) return $key;
		}
		return null;
	}	
	
	function parse_float($floatString){ 
	    if (is_numeric($floatString)) return $floatString;
	    $LocaleInfo = localeconv(); 
	    $thousep = strlen($LocaleInfo["mon_thousands_sep"]>0)?$LocaleInfo["mon_thousands_sep"]:",";
	    $decplac = strlen($LocaleInfo["mon_decimal_point"]>0)?$LocaleInfo["mon_decimal_point"]:".";
	    $newfloatString = str_replace($thousep, "", $floatString); 
	    $newfloatString = str_replace($decplac, ".", $newfloatString);
	    return floatval(preg_replace('/[^0-9.]*/','',$newfloatString)); 
	} 		
	
	// !bookmark : function populate_images (to be employed later)
	
	/*function populate_images($product_set,&$images = array()) {
		$product_set_id = '';
		foreach ($product_set as $pmap) {	
			foreach ($this->map as $mset) {
				switch ($mset['type']) {
					case 'image':
						$img = $this->get_mapped_var($pmap->spi_id,$mset['header']);
						if (array_search($img, $images) === false) {
							$images[] = $this->get_mapped_var($pmap->spi_id,$mset['header']);
							$product_set_id = $pmap->product_id;	
						}	
						break;
				}
			}
		}	
		if (strlen($product_set_id) > 0) {
			$spi_images = new spi_images($this->spi);
				$process_count = $spi_images->import_product_images($product_set_id,$images);
			unset($spi_images);
		}
		return $process_count;
	}*/
	
	function _populate_image($product_set,$pmap,$mset) {
		$process_count = 0;
		$product_set_id = $pmap->product_id;
		$img = $this->get_mapped_var($pmap->spi_id,$mset['header']);
		if (strlen($product_set_id) > 0) {
			$spi_images = new spi_images($this->spi);       
	
			 $img = $this->Shopp->Settings->get('catskin_importer_imageformat');        
			 $img = str_replace('{val}',$this->get_row_mapped_var($pmap->id,$mset['header']),$img);
			

				$process_count = $spi_images->import_product_images($product_set_id,array($img));
			unset($spi_images);
		}
		return $process_count;
	}	
	
	function process_all($status) {	
		global $wpdb;
		$query = "UPDATE {$wpdb->prefix}shopp_importer SET processing_status = {$status};";
		$result = $wpdb->query($query);
		return $result;	
	}	
	
	function process_image($id,$column_header,$column_value,$status) {
		global $wpdb;		
		$query = "UPDATE {$wpdb->prefix}shopp_importer SET processing_status = {$status} WHERE spi_id  = '{$id}' AND {$column_header} = '{$column_value}'";
		$result = $wpdb->query($query);
		return $result;	
	}	
	
	function process_product($row_id,$status) {
		global $wpdb;		
		$query = "UPDATE {$wpdb->prefix}shopp_importer SET processing_status = {$status} WHERE id = '{$row_id}'";
		$result = $wpdb->query($query);
		return $result;	
	}			
	
	function process_set($id,$status,$shopp_product_id = null) {
		global $wpdb;
		$id = trim($id);
		if (!is_null($shopp_product_id)) $prod_id = ", product_id = '{$shopp_product_id}'"; else $prod_id = "";
		$query = "UPDATE {$wpdb->prefix}shopp_importer SET processing_status = {$status} {$prod_id} WHERE spi_id = '{$id}'";
		$result = $wpdb->query($query);
		return $result;	
	}		
	
	function product_by_csv_id($csv_id) {
		foreach ($this->products as $product) {
			if ($product->csv_id == $csv_id) return $product;
		}
		return null;
	}			
	
	function product_exists($name,$sku) {
		global $wpdb;
			$query = "SELECT pd.id FROM {$wpdb->prefix}shopp_product pd, {$wpdb->prefix}shopp_price pc WHERE (pd.id = pc.product) AND (pd.name='".addslashes($name)."' AND pc.sku='{$sku}' ) LIMIT 1;";
			$result = $wpdb->get_var($query);		
		return $result;
	}		
	
	function tag_exists($name,$id) {
		global $wpdb;
			$query = "SELECT * FROM {$wpdb->prefix}shopp_tag t,{$wpdb->prefix}shopp_catalog c WHERE (t.id = c.tag) AND (t.name = '{$name}' AND c.product = '{$id}');";
			$result = $wpdb->get_row($query);		
		return $result;
	}	
}

class map_product {
	var $id;
	var $shopp_id;
	var $categories = array();
	var $description;
	var $description_text;
	var $featured;
	var $images = array();
	var $inventory;
	var $name;
	var $options = array();
	var $prices = array();
	var $price;
	var $published;
	var $sale;
	var $sale_price;
	var $ship_fee;
	var $sku;	
	var $slug;					
	var $order;
	var $stock;
	var $summary;
	var $tags = array();
	var $tax;
	var $price_type;
	var $variations = array();   
	var $specs = array();
	var $weight;	  
}

class map_category {
	var $name;
	var $value;
	var $exists;
	var $id;
	var $parent_id;
	var $slug;
	var $uri;
}

class map_tag {
	var $name;
	var $value;
	var $exists;
	var $id;	
}

class map_image {
	var $name;
	var $value;
	var $exists;
	var $id;	
}

class map_variation {
	var $name;	
	var $id;	
	var $shopp_product_id;
	var $csv_product_id;
	var $values;
}

class map_variation_value {
	var $key;
	var $value;
	var $index;
}

class map_variations {
	var $name;	
	var $value;
	var $option_id;
	var $exists;
	var $id;		
} 

class map_spec {
	var $name;
	var $value;
	var $exists;
	var $id; 
}
?>