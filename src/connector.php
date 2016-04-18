<?php

/**
 * $Id$
 *
 * SalesLayer updater class usage
 */

    require_once 'settings.php';
    require '../../vendor/autoload.php';
    use Automattic\WooCommerce\Client;
    if (!class_exists('SalesLayer_Updater')) require dirname(__FILE__).DIRECTORY_SEPARATOR.'SalesLayer-Updater.php';
    
    $dbname = DB_NAME;
    $dbhost = DB_HOST;
    $dbusername = DB_USER;
    $dbpassword = DB_PASSWORD;
    
    $CONNS=array(
        'CN1133H4648C607'=>'6919b2fd1b48ef9018ea1e1d272fed6b'
        //'__other_Sales_Layer_connector_code__'=>'__other_Sales_Layer_secret__'
    );


    // Instantiate the Sales Layer class
    $connection = new SalesLayer_Updater ($dbname, $dbusername, $dbpassword, $dbhost);
    // And the Woocommerce class
    $woocommerce = new Client('http://dev/paul/mayfield-test/', 'ck_aa0ea0a3a409e8e5afdf433e1b85273d9417bb9e', 'cs_b6d19ff8becaabdad45758bc4da85d277ea605b5');

    // Connect to the DB
     $db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASSWORD, array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
    // Create the pim_wc_connection table if it doesn't exist
    $create_table =  $db->prepare('CREATE TABLE IF NOT EXISTS pim_wc_connection(id int(11) NOT NULL AUTO_INCREMENT, pim_id int(11) NOT NULL, pim_var_id int(11) NOT NULL, pim_img_ids text DEFAULT NULL, wc_id int(11), wc_var_id int(11) NOT NULL, wc_img_ids text DEFAULT NULL, PRIMARY KEY (id)) engine=InnoDB');
    $create_table->execute();



// Begin updater
echo '<h4>Updater class version: ' . $connection->get_updater_class_version() . '</h4>';

if ($connection->has_response_error()) {

    echo '<h4>Error:</h4>\n\n Code: ' . $connection->get_response_error() .
        "<br>\nMessage: " . $connection->get_response_error_message();

} else {

    foreach($CONNS as $codeConn => $secretKey) {

        $connection->set_identification($codeConn, $secretKey);

        echo "<h3>Connector: $codeConn</h3>\n";

        // Updater!
        $connection->update();

        if ($connection->has_response_error()) {

            echo "<h4>Error:</h4>\n\n Code: " . $connection->get_response_error() .
                "<br>\nMessage: " . $connection->get_response_error_message();

        } else {


        //
        // Basic information about the connector and how many modifications and deletions are taking place
        //
            $modified_product_ids_count = count($connection->get_response_table_modified_ids('products'));
            $modified_format_ids_count = count($connection->get_response_table_modified_ids('product_formats'));
            $deleted_product_ids_count = count($connection->get_response_table_deleted_ids('products'));
            $deleted_format_ids_count = count($connection->get_response_table_deleted_ids('product_formats'));

            // Display version information and number of products being changed
            echo "<h4>Response OK</h4>\n" .
                "<p>" .
                "API version: <b>" . $connection->get_response_api_version() . "</b><br />\n" .
                "Action: <b>" . $connection->get_response_action() . "</b><br />\n" .
                "Time: <b>" . $connection->get_response_time() . "</b> (GMT 0)<br /><br />\n";
            if ($modified_product_ids_count > 0){echo'Number of <strong>modified</strong> products: '.$modified_product_ids_count;echo'<br/>';$modifications=true;}
            if ($modified_format_ids_count > 0){echo'Number of <strong>modified</strong> formats: '.$modified_format_ids_count;echo'<br/>';$modifications=true;}
            if ($deleted_product_ids_count > 0) {echo'Number of <strong>deleted</strong> products: '.$deleted_product_ids_count;echo'<br/>';$modifications = true;}
            if ($deleted_format_ids_count > 0) {echo'Number of <strong>deleted</strong> formats: '.$deleted_format_ids_count;echo'<br/>';$modifications = true;}
            echo '<hr><br/>';


        //
        // Pull apart the data and send it away to a custom function to be formatted for insertion to Woocommerce
        //
            if (isset($modifications) && $modifications === true) {

                //
                // Modified products and formats
                if ($modified_product_ids_count > 0 || $modified_format_ids_count > 0) {
                    
                    echo '<strong>Modified Products</strong><br/><br/>';

                    if($modified_product_ids_count > 0){$type='product';$modified_products = $connection->get_response_table_data('products');}
                    if($modified_format_ids_count > 0){$type='product_format';$modified_formats = $connection->get_response_table_data('product_formats');}

					// Loop through the products and add them to Woocommerce
                    if(isset($modified_products)) {
                        
                        $all_mod_prods = $modified_products['modified'];
                        //echo'<pre>';var_dump($connection->get_response_table_data());echo'</pre>';die;
                        $i = 0;
                        foreach ($all_mod_prods as $individual_product) {
                            
                            $all_formatted_products[$i] = process_product($individual_product);
                            ++$i;
                        }
                    }
					
					// Loop through the formats, add them to their product and add all the Woocommerce
                    if(isset($modified_formats)) {
                        
                        $all_mod_formats = $modified_formats['modified'];
                        foreach($all_mod_formats as $individual_format) {

							process_format($individual_format, $all_formatted_products);
                        }
                    }


                    if ($deleted_product_ids_count > 0 || $deleted_format_ids_count > 0) {
                        echo '<hr>';
                    }
                }

                //
                // Deleted products and formats
                if ($deleted_product_ids_count > 0 || $deleted_format_ids_count > 0) {

                    $deleted_ids = $connection->get_response_table_deleted_ids();

                    process_deletions($deleted_product_ids_count, $deleted_format_ids_count, $deleted_ids);

                }
            }
            else {

                echo '<strong>No changes have been made.</strong>';

            }
        }
    }
    // Display debug information
    //$connection->print_debbug();
}


function process_product($individual_product) {
	// Connections
    $db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASSWORD, array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
    $woocommerce = new Client('http://dev/paul/mayfield-test/', 'ck_aa0ea0a3a409e8e5afdf433e1b85273d9417bb9e', 'cs_b6d19ff8becaabdad45758bc4da85d277ea605b5');

    // Format data to accommodate woocommerce
    $individual_formatted_product = format_product($individual_product);

    $pim_id = $individual_product['id'];
    
    if(isset($individual_formatted_product['product']['pim_img_ids'])) {
	    $pim_img_ids = $individual_formatted_product['product']['pim_img_ids'];
    }


    // Check if product already exists and either run a Create or Update
    $check_prod =  $db->query('SELECT * FROM pim_wc_connection WHERE pim_id = '.$pim_id.' LIMIT 1');
    $row_count = $check_prod->rowCount();
    if($row_count > 0) {
	    $result = $check_prod->fetch(PDO::FETCH_ASSOC);
	    $process_type = 'Updated';
        $wc_id = $result['wc_id'];
        
        // Modify in Woocommerce
        //echo'<pre>';var_dump($individual_formatted_product);echo'</pre><br/><br/>';
        $return_data =  $woocommerce->put('products/'.$wc_id, $individual_formatted_product);

        $individual_formatted_product['product']['id'] = $wc_id;
        
        if(isset($pim_img_ids)) {
	        $all_wc_images = $return_data['product']['images'];
            $wc_img_ids = '';
            foreach($all_wc_images as $wc_image) {
                $wc_img_ids = $wc_img_ids.$wc_image['id'].',';
            }
        }
		
		// Add to the database
        if(isset($wc_img_ids)) {
            $update_product = $db->prepare('UPDATE pim_wc_connection SET pim_img_ids=?, wc_img_ids=? WHERE pim_id=?');
            $update_product->execute(array($pim_img_ids, $wc_img_ids, $pim_id));
        }

    } else {
	    $process_type = 'Created';

        // On rare occasions Woocommerce will leave as sku in the wc_postmeta table, making it impossible to add this sku again
        // Here we check for the sku which we are creating and, if it exists, delete it
        $sku_id = $individual_formatted_product['product']['sku'];
        $check_wc_sku = $db->query('SELECT * FROM wp_postmeta WHERE meta_key = "_sku" AND meta_value = "'.$sku_id.'"');
        $row_count = $check_wc_sku->rowCount();
        if($row_count == 0) {
            $delete_sku = $db->prepare('DELETE FROM wp_postmeta WHERE meta_key = "_sku" AND meta_value = ? LIMIT 1');
            $delete_sku->execute(array($sku_id));
        }

        // Create in Woocommerce
        $return_data =  $woocommerce->post('products', $individual_formatted_product);
        $wc_id = $return_data['product']['id'];
        $individual_formatted_product['product']['id'] = $wc_id;

        if(isset($pim_img_ids)) {
	        $all_wc_images = $return_data['product']['images'];
            $wc_img_ids = '';
            foreach($all_wc_images as $wc_image) {
                $wc_img_ids = $wc_img_ids.$wc_image['id'].',';
            }
        }

        // Create in pim_wc_connection
        if(isset($wc_img_ids)) {
            $create_product = $db->prepare('INSERT INTO pim_wc_connection(pim_id, pim_img_ids, wc_id, wc_img_ids) VALUES(?,?,?,?)');
            $create_product->execute(array($pim_id, $pim_img_ids, $wc_id, $wc_img_ids));
        } else {
            $create_product = $db->prepare('INSERT INTO pim_wc_connection(pim_id, wc_id) VALUES(?,?)');
            $create_product->execute(array($pim_id, $wc_id));
        }
    }
    
    // Print the product on the screen
    echo $process_type.' in WC: '.$individual_formatted_product['product']['title'].'<br/>';
    echo $pim_id.' - '.$wc_id;
    echo'</br></br>';

    Return $individual_formatted_product;
}


function process_format($individual_format, $all_formatted_products) {
	// Connections
    $db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASSWORD, array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
    $woocommerce = new Client('http://dev/paul/mayfield-test/', 'ck_aa0ea0a3a409e8e5afdf433e1b85273d9417bb9e', 'cs_b6d19ff8becaabdad45758bc4da85d277ea605b5');

    $pim_var_id = $individual_format['id'];
	foreach($all_formatted_products as $key => $formatted_product) {

        $wc_id = $formatted_product['product']['id'];

        $get_pim_id =  $db->query('SELECT * FROM pim_wc_connection WHERE wc_id = '.$wc_id.' LIMIT 1');
        $result = $get_pim_id->fetch(PDO::FETCH_ASSOC);

        $pim_id = $result['pim_id'];

		// Loop through all the products and find the one that applies to this format                 
		if($pim_id == $individual_format['products_id']) {

            $get_wc_var_id =  $db->query('SELECT * FROM pim_wc_connection WHERE pim_var_id = '.$pim_var_id.' LIMIT 1');
            $result = $get_wc_var_id->fetch(PDO::FETCH_ASSOC);
            if(count($result) > 0) {
                $individual_format['wc_var_id'] = $result['wc_var_id'];
            }

            $prod_pim_id = $pim_id;
            $prod_wc_id = $wc_id;

			$attr_count = count($formatted_product['product']['attributes']);
            if(isset($formatted_product['product']['variations']) && !empty($formatted_product['product']['variations'])) {
                $format_count = count($formatted_product['product']['variations']);
            } else {
                $format_count = 0;
            }

            $all_formatted_products[$key] = format_format($individual_format, $all_formatted_products, $attr_count, $format_count);

		}
	}

    // As with products, we want to check that the sku for the format we're creating hasn't been left in wp_postmeta - but only if this is a new format
    $check_pim_var = $db->query('SELECT * FROM pim_wc_connection WHERE pim_var_id = '.$pim_var_id);
    $row_count = $check_pim_var->rowCount();
    if($row_count == 0) {

        $sku_id = $all_formatted_products[$key]['product']['variations'][$format_count]['sku'];
        $check_wc_sku = $db->query('SELECT * FROM wp_postmeta WHERE meta_key = "_sku" AND meta_value = "' . $sku_id.'"');
        $row_count = $check_wc_sku->rowCount();

        if ($row_count > 0) {
            $delete_sku = $db->prepare('DELETE FROM wp_postmeta WHERE meta_key = "_sku" AND meta_value = ? LIMIT 1');
            $delete_sku->execute(array($sku_id));
        }
    }

    // Push this into woocommerce and return the id of the format
    //echo'<pre>';var_dump($finished_product[0]);echo'</pre><br/><br/>';
    $return_data = $woocommerce->put('products/'.$prod_wc_id, $all_formatted_products[$key]);
    //echo'<pre>';var_dump($return_data);echo'</pre>';die;

    // Set the wc var id
    $wc_var_id = $return_data['product']['variations'][$format_count]['id'];
    
    // Check if the var already exists in the db and, if not; add it
    $check_format = $db->query('SELECT * FROM pim_wc_connection WHERE pim_var_id = '.$pim_var_id.' LIMIT 1');
    $row_count = $check_format->rowCount();
    if($row_count == 0) {
        $create_format = $db->prepare('INSERT INTO pim_wc_connection(pim_id, pim_var_id, wc_id, wc_var_id) values(?,?,?,?)');
	    $create_format->execute(array($prod_pim_id, $pim_var_id, $prod_wc_id, $wc_var_id));
	}
    
}


function process_deletions($deleted_product_ids_count, $deleted_format_ids_count, $deleted_ids) {
	
    if($deleted_product_ids_count > 0) {
		// Delete products
        $deleted_product_ids = $deleted_ids['products'];

        echo '<strong>Deleted Products</strong><br/>';

        foreach($deleted_product_ids as $deleted_product_id) {

            $delete_type = 'product';
            $wc_id = get_deleted_id_and_delete($delete_type, $deleted_product_id);

            echo $deleted_product_id.' - '.$wc_id.'<br/>';
        }
        echo '<br/>';
    }
    if($deleted_format_ids_count > 0) {
		// Delete formats
        $deleted_format_ids = $deleted_ids['product_formats'];

        echo '<strong>Deleted Formats</strong><br/>';

        foreach($deleted_format_ids as $deleted_format_id) {

            $delete_type = 'format';
            $wc_id = get_deleted_id_and_delete($delete_type, $deleted_format_id);

            echo $deleted_format_id.' - '.$wc_id.'<br/>';
        }
    }
}


function get_deleted_id_and_delete($delete_type, $deleted_id) {
	// Connections
    $db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASSWORD, array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
    $woocommerce = new Client('http://dev/paul/mayfield-test/', 'ck_aa0ea0a3a409e8e5afdf433e1b85273d9417bb9e', 'cs_b6d19ff8becaabdad45758bc4da85d277ea605b5');
	
	// Set the type of id to search for
    if($delete_type == 'product'){$id_type = 'pim_id';}elseif($delete_type == 'format'){$id_type = 'pim_var_id';}
    
    // Search for the WC version of the id depending on above, and put it into the wc_id variable
    $delete_id =  $db->query('SELECT * FROM pim_wc_connection WHERE '.$id_type.' = '.$deleted_id.' LIMIT 1');
    $result = $delete_id->fetch(PDO::FETCH_ASSOC);
    if($delete_type == 'product'){$wc_id = $result['wc_id'];}elseif($delete_type == 'format'){$wc_id = $result['wc_var_id'];}

    // Delete from the database
    $delete_from_db =  $db->prepare('DELETE FROM pim_wc_connection WHERE '.$id_type.' = '.$deleted_id.'');
    $delete_from_db->execute();

    // Delete from Woocommerce
     $woocommerce->delete('products/'.$wc_id, ['force' => true]);

    return $wc_id;
}


/*
 *	-----
 *  -------------
 *  Custom formatting dependant on PIM company - You only need to edit the code below to customise this connector
 *  -------------
 *	-----
 */
 
function format_product($individual_product){

	$db = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASSWORD, array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));

    $wc_product['product']['type'] = 'simple';
		
	// Add all of the PIM information into an array which will then be sent to woocommerce for upload
	$wc_product['product']['title'] = $individual_product['data']['product_name'];
	$wc_product['product']['sku'] = $individual_product['data']['product_reference'];
	$wc_product['product']['description'] = '';
	$wc_product['product']['short_description'] = $individual_product['data']['product_description_en'];
	
	// Use type_of_product to check the category name against a list of woocommerce categories and get the id
	$wc_category_ids = wc_category_ids($individual_product['catalogue_id']);
	$wc_product['product']['categories'] = $wc_category_ids;

	$id = $individual_product['id'];
	$getProd = $db->query('SELECT * FROM pim_wc_connection WHERE pim_id = '.$id.' LIMIT 1');
	$results = $getProd->fetch(PDO::FETCH_ASSOC);
	
	$pim_img_ids = $results['pim_img_ids'];
	$pim_img_array = explode(',', $pim_img_ids);
	
	// Loop through all of the images
	$i = 0;
	if(!empty($individual_product['data']['product_image'])) {
		$images = $individual_product['data']['product_image'];
		$wc_product['product']['pim_img_ids'] = '';
		foreach($images as $image) {
			$img_id = array_keys($images);
			if(!in_array($img_id[$i], $pim_img_array)) {
				$wc_product['product']['images'][$i]['title'] = $img_id[$i];
				$wc_product['product']['images'][$i]['alt'] = $img_id[$i];
				$wc_product['product']['images'][$i]['src'] = $image['THM'];
				$wc_product['product']['images'][$i]['position'] = $i;
			}
			$all_prod_img_ids[$i] =  $wc_product['product']['pim_img_ids'].$img_id[$i];
			++$i;
		}
		$wc_product['product']['pim_img_ids'] = implode(',', $all_prod_img_ids);
	}
	
	// Add all of the attributes to the array for woocommerce
	$wc_product['product']['attributes'][0]['name'] = 'Eca Approved';
	$wc_product['product']['attributes'][0]['options'] = $individual_product['data']['eca_approved'];
	$wc_product['product']['attributes'][1]['name'] = 'Ec Fan Motors';
	$wc_product['product']['attributes'][1]['options'] = $individual_product['data']['ec_fan_motors'];
	$wc_product['product']['attributes'][2]['name'] = 'Led Canopy Lighting';
	$wc_product['product']['attributes'][2]['options'] = $individual_product['data']['led_canopy_lighting'];
	$wc_product['product']['attributes'][3]['name'] = 'Manual Night Blinds';
	$wc_product['product']['attributes'][3]['options'] = $individual_product['data']['manual_night_blinds'];

	return $wc_product;

}


function format_format($individual_format, $formatted_product, $attr_count, $format_count) {

    $formatted_product[0]['product']['type'] = 'variable';

	// Set the attribute for this format
	$formatted_product[0]['product']['attributes'][$attr_count]['name'] = $individual_format['data']['format_reference'];
    $formatted_product[0]['product']['attributes'][$attr_count]['options'] = $individual_format['data']['format_reference'];
    $formatted_product[0]['product']['attributes'][$attr_count]['variation'] = true;

    // Set the format details
    if(isset($individual_format['wc_var_id'])){
        $formatted_product[0]['product']['variations'][$format_count]['id'] = $individual_format['wc_var_id'];
    }

    $formatted_product[0]['product']['variations'][$format_count]['sku'] = $individual_format['data']['format_reference'];

    $formatted_product[0]['product']['variations'][$format_count]['attributes'][0]['name'] = $individual_format['data']['format_reference'];;
    $formatted_product[0]['product']['variations'][$format_count]['attributes'][0]['options'] = $individual_format['data']['format_reference'];

    if(isset($individual_format['data']['dimensions']) && !empty($individual_format['data']['dimensions'])) {
        $downloads = $individual_format['data']['dimensions'];
        $download_ids = array_keys($downloads);
        $download_id = $download_ids[0];

        $file_path = $individual_format['data']['dimensions'][$download_id]['THM'];

        $formatted_product[0]['product']['variations'][$format_count]['downloadable'] = true;
        $formatted_product[0]['product']['variations'][$format_count]['downloads'][0]['id'] = $download_id;
        $formatted_product[0]['product']['variations'][$format_count]['downloads'][0]['name'] = basename($file_path);
        $formatted_product[0]['product']['variations'][$format_count]['downloads'][0]['file'] = $file_path;
    }

	return $formatted_product;

}


function wc_category_ids($input_categorys) {
	$category_array = array();
		
	foreach($input_categorys as $input_category) {
		$categories = array(
			'pim_cat_id_1' => 'wc_cat_id_1', //Name of category
			'pim_cat_id_2' => 'wc_cat_id_2', //Name of category
				'pim_cat_id_3' => 'wc_cat_id_3', //Name of subcategory
				'pim_cat_id_4' => 'wc_cat_id_4', //Name of subcategory
			'pim_cat_id_5' => 'wc_cat_id_5' //Name of category
		);
		foreach($categories as $key => $value) {
			if($input_category == $key) {
				array_push($category_array, $value);
			}
		}
	}
	
	return $category_array;
}