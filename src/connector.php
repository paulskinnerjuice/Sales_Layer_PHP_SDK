<?php

/**
 * $Id$
 *
 * SalesLayer updater class usage
 */

require_once 'settings.php';
if (!class_exists('SalesLayer_Updater')) require dirname(__FILE__).DIRECTORY_SEPARATOR.'SalesLayer-Updater.php';

$dbname = DB_NAME;
$dbhost = DB_HOST;
$dbusername = DB_USER;
$dbpassword = DB_PASSWORD;

$CONNS=array(

    'CN1133H4648C607'=>'6919b2fd1b48ef9018ea1e1d272fed6b',
    //'__other_Sales_Layer_connector_code__'=>'__other_Sales_Layer_secret__'
);

//public function run_updater() {

    // Instantiate the class
    $connection = new SalesLayer_Updater ($dbname, $dbusername, $dbpassword, $dbhost);

    $db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASSWORD, array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));

    echo '<h4>Updater class version: ' . $connection->get_updater_class_version() . '</h4>';

    if ($connection->has_response_error()) {

        echo '<h4>Error:</h4>\n\n Code: ' . $connection->get_response_error() .
            "<br>\nMessage: " . $connection->get_response_error_message();

    } else {

        foreach ($CONNS as $codeConn => $secretKey) {

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
                if ($modified_product_ids_count > 0) {
                    echo 'Number of <strong>modified</strong> products: ' . $modified_product_ids_count;
                    echo '<br/>';
                    $modifications = true;
                }
                if ($modified_format_ids_count > 0) {
                    echo 'Number of <strong>modified</strong> formats: ' . $modified_format_ids_count;
                    echo '<br/>';
                    $modifications = true;
                }
                if ($deleted_product_ids_count > 0) {
                    echo 'Number of <strong>deleted</strong> products: ' . $deleted_product_ids_count;
                    echo '<br/>';
                    $modifications = true;
                }
                if ($deleted_format_ids_count > 0) {
                    echo 'Number of <strong>deleted</strong> formats: ' . $deleted_format_ids_count;
                    echo '<br/>';
                    $modifications = true;
                }
                echo '<hr><br/>';

            //
            // Pull apart the data and send it away to a custom function to be formatted for insertion to Woocommerce
            //
                if (isset($modifications) && $modifications === true) {
                    // Modified products and formats
                    if ($modified_product_ids_count > 0 || $modified_format_ids_count > 0) {

                        $results = $connection->get_response_table_data();
                        echo '<pre>';
                        var_dump($results);
                        echo '</pre>';

                        /*
                        $modified_products = $results['products']['modified'];

                        foreach($modified_products as $modified_product) {
                            echo'<strong>'.$modified_product['data']['product_name'].'</strong>';
                            echo '<pre>';
                            print_r($modified_product['data']);
                            echo '</pre><br/><br/>';
                        }

                        if ($deleted_product_ids_count > 0 || $deleted_format_ids_count > 0) {
                            echo '<hr>';
                        }
                        */
                    }

                    // Deleted products and formats
                    if ($deleted_product_ids_count > 0 || $deleted_format_ids_count > 0) {

                        $deleted_ids = $connection->get_response_table_deleted_ids();

                        if($deleted_product_ids_count > 0) {

                            $deleted_product_ids = $deleted_ids['products'];

                            echo '<strong>Deleted Products</strong><br/>';

                            foreach($deleted_product_ids as $deleted_product_id) {

                                $delete_type = 'product';
                                $wc_id = $this->get_deleted_id($delete_type, $deleted_product_id);

                                // Delete the product from Woocommerce

                                echo $deleted_product_id.' - '.$wc_id.'<br/>';
                            }
                            echo '<br/>';
                        }
                        if($deleted_format_ids_count > 0) {

                            $deleted_format_ids = $deleted_ids['product_formats'];

                            echo '<strong>Deleted Formats</strong><br/>';

                            foreach($deleted_format_ids as $deleted_format_id) {

                                $delete_type = 'format';
                                $wc_id = $this->get_deleted_id($delete_type, $deleted_format_id);

                                // Delete the product from Woocommerce

                                echo $deleted_format_id.' - '.$wc_id.'<br/>';
                            }
                        }
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
//}

private function get_deleted_id($delete_type, $deleted_id) {

    $db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASSWORD, array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));

    if($delete_type == 'product')
    // Search pim_wc_connection table for deleted_id and return the wc_id

    // Delete from the database

}

/*
 * pim_wc_connection table columns:
 *      id          -   int(11)
 *      pim_id      -   int(11)
 *      pim_var_id  -   int(11)
 *      pim_img_ids -   text
 *      wc_id       -   int(11)
 *      wc_var_id   -   int(11)
 *      wc_img_ids  -   text
 */