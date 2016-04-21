<?php

require_once 'connections/connections.php';
require_once 'processes/process_products.php';

class Pim_Wc_Process
{
    public function __construct()
    {
        $this->connection = new Services_Connection();
        $this->process = new Process_Products();

        $this->connection->pim_connect();
        $this->connection->pim_conns();
        $this->connection->wc_connect();
        $this->connection->db_connect();
    }

    public function run_updater()
    {
        $CONNS = $this->connection->get_pim_conns();
        $pim = $this->connection->get_pim_connection();

        // Begin updater
        echo '<h4>Updater class version: ' . $pim->get_updater_class_version() . '</h4>';

        if ($pim->has_response_error()) {

            echo '<h4>Error:</h4>\n\n Code: ' . $pim->get_response_error() .
                "<br>\nMessage: " . $pim->get_response_error_message();

        } else {

            foreach ($CONNS as $codeConn => $secretKey) {

                $pim->set_identification($codeConn, $secretKey);

                echo "<h3>Connector: $codeConn</h3>\n";

                // Updater!
                $pim->update();

                if ($pim->has_response_error()) {

                    echo "<h4>Error:</h4>\n\n Code: " . $pim->get_response_error() .
                        "<br>\nMessage: " . $pim->get_response_error_message();

                } else {

                    $stats = $this->get_update_stats();
                    
                    // Pull apart the data and send it away to a custom function to be formatted for insertion to Woocommerce
                    if (isset($stats['modifications']) && $stats['modifications'] === true) {

                        //
                        // Deleted products and formats
                        if ($stats['deleted_product_ids_count'] > 0 || $stats['deleted_format_ids_count'] > 0) {

                            $deleted_ids = $pim->get_response_table_deleted_ids();

                            $this->process->process_deletions($stats['deleted_product_ids_count'], $stats['deleted_format_ids_count'], $deleted_ids);

                            if ($stats['modified_product_ids_count'] > 0 || $stats['modified_format_ids_count'] > 0) {
                                echo '<hr>';
                            }

                        }

                        //
                        // Modified products and formats
                        if ($stats['modified_product_ids_count'] > 0 || $stats['modified_format_ids_count'] > 0) {

                            echo '<strong>Modified Products</strong><br/><br/>';

                            if ($stats['modified_product_ids_count'] > 0) {
                                $modified_products = $pim->get_response_table_data('products');
                            }
                            if ($stats['modified_format_ids_count'] > 0) {
                                $modified_formats = $pim->get_response_table_data('product_formats');
                            }

                            // Loop through the products and add them to Woocommerce
                            if (isset($modified_products)) {

                                $all_mod_prods = $modified_products['modified'];
                                //echo'<pre>';var_dump($pim->get_response_table_data());echo'</pre>';die;
                                $i = 0;
                                foreach ($all_mod_prods as $individual_product) {

                                    if(isset($modified_formats)) {
                                        $all_mod_formats = $modified_formats['modified'];
                                        $individual_product['attribute_skus'] = $this->get_format_skus($individual_product, $all_mod_formats);
                                    }

                                    $all_formatted_products[$i] = $this->process->process_product($individual_product);
                                    ++$i;
                                }
                            }
                            
                            // Loop through the formats, add them to their product and add all to Woocommerce
                            if (isset($modified_formats)) {

                                $all_mod_formats = $modified_formats['modified'];
                                foreach ($all_mod_formats as $individual_format) {
                                    $new_formatted_products = $this->process->process_format($individual_format, $all_formatted_products);
                                    $all_formatted_products = $new_formatted_products;
                                }
                            }
                        }
                        
                    } else {
                        echo '<strong>No changes have been made.</strong>';
                    }
                }
            }
            // Display debug information
            //$pim->print_debbug();
        }
    }
    
    private function get_update_stats()
    {
        $pim = $this->connection->get_pim_connection();

        // Basic information about the connector and how many modifications and deletions are taking place
        $stats['deleted_product_ids_count'] = count($pim->get_response_table_deleted_ids('products'));
        $stats['deleted_format_ids_count'] = count($pim->get_response_table_deleted_ids('product_formats'));
        $stats['modified_product_ids_count'] = count($pim->get_response_table_modified_ids('products'));
        $stats['modified_format_ids_count'] = count($pim->get_response_table_modified_ids('product_formats'));

        // Display version information and number of products being changed
        echo "<h4>Response OK</h4>\n" .
            "<p>" .
            "API version: <b>" . $pim->get_response_api_version() . "</b><br />\n" .
            "Action: <b>" . $pim->get_response_action() . "</b><br />\n" .
            "Time: <b>" . $pim->get_response_time() . "</b> (GMT 0)<br /><br />\n";
        
        if ($stats['modified_product_ids_count'] > 0) {
            echo 'Number of <strong>modified</strong> products: ' . $stats['modified_product_ids_count'];
            echo '<br/>';
            $stats['modifications'] = true;
        }
        if ($stats['modified_format_ids_count'] > 0) {
            echo 'Number of <strong>modified</strong> formats: ' . $stats['modified_format_ids_count'];
            echo '<br/>';
            $stats['modifications'] = true;
        }
        if ($stats['deleted_product_ids_count'] > 0) {
            echo 'Number of <strong>deleted</strong> products: ' . $stats['deleted_product_ids_count'];
            echo '<br/>';
            $stats['modifications'] = true;
        }
        if ($stats['deleted_format_ids_count'] > 0) {
            echo 'Number of <strong>deleted</strong> formats: ' . $stats['deleted_format_ids_count'];
            echo '<br/>';
            $stats['modifications'] = true;
        }
        
        echo '<hr><br/>';
        return $stats;
    }

    private function get_format_skus($individual_product, $modified_formats){

        $wc = $this->connection->get_wc_connection();
        $db = $this->connection->get_db_connection();

        $attribute_skus = array();
        
        // Retrieve any current attributes for the product and add them to the above array
        $pim_id = $individual_product['id'];
        $get_wc_id = $db->query('SELECT * FROM pim_wc_connection WHERE pim_id = ' . $pim_id . ' LIMIT 1');
        $row_count = $get_wc_id->rowCount();
        if ($row_count > 0) {
            $result = $get_wc_id->fetch(PDO::FETCH_ASSOC);
            $wc_id = $result['wc_id'];
            $wc_result = $wc->get('products/'.$wc_id);
            $all_attrs = $wc_result['product']['attributes'];
            foreach($all_attrs as $attr) {
                if($attr['name'] == 'Variations') {
                    $attribute_skus = $attr['options'];
                }
            }
        }
        
        // Get all skus from updated formats and add them to the above array only if they're not already in there
        foreach($modified_formats as $indiv_format) {
            if($indiv_format['products_id'] == $individual_product['id']) {
                $format = str_replace(' ', '_', $indiv_format['data']['format_reference']);
                if(!in_array($format, $attribute_skus)) {
                    array_push($attribute_skus, $format);
                }
            }
        }
        return $attribute_skus;
    }
}