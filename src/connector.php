<?php

/**
 * $Id$
 *
 * SalesLayer updater class usage
 */

    require_once 'settings.php';
    require '../vendor/autoload.php';
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
    $create_table =  $db->prepare('CREATE TABLE IF NOT EXISTS pim_wc_connection(id int(11) NOT NULL AUTO_INCREMENT, pim_id int(11) NOT NULL, pim_var_id int(11) NOT NULL, pim_img_ids int(11), wc_id int(11), wc_var_id int(11), wc_img_ids int(11), PRIMARY KEY (id)) engine=InnoDB');
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

                        if($modified_product_ids_count > 0){$type='product';$modified_products = $connection->get_response_table_data('products');}
                        if($modified_format_ids_count > 0){$type='product_format';$modified_formats = $connection->get_response_table_data('product_formats');}


                        if(isset($modified_products)) {
                            $all_mod_prods = $modified_products['modified'];
                            $i = 0;
                            foreach ($all_mod_prods as $individual_product) {
                                $all_formatted_products[$i] = process_product($individual_product);
                                ++$i;
                            }
                        }

                        if(isset($modified_formats)) {
                            $all_mod_formats = $modified_formats['modified'];
                            $i = 0;
                            foreach($all_mod_formats as $individual_format) {
                                process_format($individual_format, $all_formatted_products);
                                ++$i;
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


// Functions

function process_product($individual_product) {
    $db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASSWORD, array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
    $woocommerce = new Client('http://dev/paul/mayfield-test/', 'ck_aa0ea0a3a409e8e5afdf433e1b85273d9417bb9e', 'cs_b6d19ff8becaabdad45758bc4da85d277ea605b5');

    $check_product_exists =  $db->prepare('SELECT * FROM pim_wc_connection WHERE pim_id = ? LIMIT 1');
    $check_wc_sku =  $db->prepare('SELECT * FROM wp_postmeta WHERE meta_key = _sku AND meta_value = ?');

    // Format data to accommodate woocommerce
    $individual_formatted_product = format_product($individual_product);

    $pim_id = $individual_formatted_product['product']['id'];
    $pim_img_ids = $individual_formatted_product['product']['pim_img_ids'];


    // Check if product already exists - also check wp_postmeta sku and delete any conflicts if it doesn't exist in pim_wc_connection
    $check_product_exists->execute(array($pim_id));
    $results = $check_product_exists->fetchAll(PDO::FETCH_ASSOC);
    if(count($results) > 0) {
        $wc_id = $results['wc_id'];
        $return_data =  $woocommerce->put('products/'.$wc_id, $individual_formatted_product);
        $all_wc_images = $return_data['product']['images'];
        if(!empty($all_wc_images)) {
            $wc_img_ids = '';
            foreach($all_wc_images as $wc_image) {
                $wc_img_ids = $wc_img_ids.$wc_image['id'].',';
            }
        }

        if(isset($wc_img_ids)) {
            $update_product =  $db->prepare('UPDATE pim_wc_connection SET pim_img_ids=?, wc_img_ids=? WHERE pim_id=?');
            $update_product->execute(array($pim_img_ids, $wc_img_ids));
        }

    } else {
        $sku_id = $individual_formatted_product['product']['product_reference'];
        $check_wc_sku->execute(array($sku_id));
        $sku_results = $check_wc_sku->fetchAll(PDO::FETCH_ASSOC);
        if(count($sku_results) > 0) {
            $delete_sku =  $db->prepare('DELETE FROM wp_postmeta WHERE meta_key = _sku AND meta_value = ?');
            $delete_sku->execute(array($sku_id));
        }

        // Create in Woocommerce
        $return_data =  $woocommerce->post('products', $individual_formatted_product);

        $wc_id = $return_data['product']['id'];
        $all_wc_images = $return_data['product']['images'];
        if(!empty($all_wc_images)) {
            $wc_img_ids = '';
            foreach($all_wc_images as $wc_image) {
                $wc_img_ids = $wc_img_ids.$wc_image['id'].',';
            }
        }

        // Create in pim_wc_connection
        if(isset($wc_img_ids)) {
            $create_product =  $db->prepare('INSERT INTO pim_wc_connection(pim_id, pim_img_ids, wc_id, wc_img_ids) VALUES(?,?,?,?)');
            $create_product->execute(array($pim_id, $pim_img_ids, $wc_id, $wc_img_ids));
        } else {
            $create_product =  $db->prepare('INSERT INTO pim_wc_connection(pim_id, wc_id) VALUES(?,?)');
            $create_product->execute(array($pim_id, $wc_id));
        }
    }

    Return $individual_formatted_product;
}


function process_format($individual_format, $all_formatted_products = false) {
    $db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASSWORD, array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
    $woocommerce = new Client('http://dev/paul/mayfield-test/', 'ck_aa0ea0a3a409e8e5afdf433e1b85273d9417bb9e', 'cs_b6d19ff8becaabdad45758bc4da85d277ea605b5');

    $check_format_exists =  $db->prepare('SELECT * FROM pim_wc_connection WHERE pim_var_id = ? LIMIT 1');
    $check_wc_sku =  $db->prepare('SELECT * FROM wp_postmeta WHERE meta_key = _sku AND meta_value = ?');

    // Format data to accommodate woocommerce
    if(isset($all_formatted_products) && $all_formatted_products == true) {
        $individual_formatted_format = format_format($individual_format, $all_formatted_products);
    } else {
        $individual_formatted_format = format_format($individual_format);
    }

    $pim_id = $individual_formatted_format['product']['id'];
    $pim_img_ids = $individual_formatted_format['product']['pim_img_ids'];


    // Check if product already exists - also check wp_postmeta sku and delete any conflicts if it doesn't exist in pim_wc_connection
        // If so, send as update - update database
        // If not, send as update - add into database
}


function process_deletions($deleted_product_ids_count, $deleted_format_ids_count, $deleted_ids) {

    if($deleted_product_ids_count > 0) {

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
    $db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASSWORD, array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
    $woocommerce = new Client('http://dev/paul/mayfield-test/', 'ck_aa0ea0a3a409e8e5afdf433e1b85273d9417bb9e', 'cs_b6d19ff8becaabdad45758bc4da85d277ea605b5');

    $delete_id =  $db->prepare('SELECT * FROM pim_wc_connection WHERE ? = ? LIMIT 1');

    // Set the type of id to search for
    if($delete_type == 'product'){$id_type = 'pim_id';}elseif($delete_type == 'format'){$id_type = 'pim_var_id';}

    // Search for the WC version of the id depending on above, and put it into the wc_id variable
    $delete_id->execute(array($id_type, $deleted_id));
    $results = $delete_id->fetchAll(PDO::FETCH_ASSOC);
    if($delete_type == 'product'){$wc_id = $results['wc_id'];}elseif($delete_type == 'format'){$wc_id = $results['wc_var_id'];}

    // Delete from the database
    $delete_from_db =  $db->prepare('DELETE FROM pim_wc_connection WHERE ? = ?');
    $delete_from_db->execute(array($id_type, $deleted_id));

    // Delete from Woocommerce
     $woocommerce->delete('products/'.$wc_id, ['force' => true]);

    return $wc_id;
}


/*
 *  ---
 *  Custom formatting dependant on PIM company
 *  ---
 */
function format_product($individual_product){



}


function format_format($individual_format, $all_formatted_products) {



}