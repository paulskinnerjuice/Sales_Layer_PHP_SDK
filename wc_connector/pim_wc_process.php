<?php

require_once 'connections/connections.php';
require_once 'processes/process_products.php';

class Pim_Wc_Process
{
    public function __construct()
    {
        $connection = new Services_Connection();
        $this->process = new Process_Products();

        $this->db = $connection->db_connect();

        $pim_connect = $connection->pim_connect();
        $this->pim = $pim_connect['pim'];
        $this->CONNS = $pim_connect['CONNS'];

        $this->wc = $connection->wc_connect();
    }

    public function run_updater()
    {

        // Begin updater
        echo '<h4>Updater class version: ' . $this->pim->get_updater_class_version() . '</h4>';

        if ($this->pim->has_response_error()) {

            echo '<h4>Error:</h4>\n\n Code: ' . $this->pim->get_response_error() .
                "<br>\nMessage: " . $this->pim->get_response_error_message();

        } else {

            foreach ($this->CONNS as $codeConn => $secretKey) {

                $this->pim->set_identification($codeConn, $secretKey);

                echo "<h3>Connector: $codeConn</h3>\n";

                // Updater!
                $this->pim->update();

                if ($this->pim->has_response_error()) {

                    echo "<h4>Error:</h4>\n\n Code: " . $this->pim->get_response_error() .
                        "<br>\nMessage: " . $this->pim->get_response_error_message();

                } else {

                    $stats = $this->get_update_stats();
                    
                    // Pull apart the data and send it away to a custom function to be formatted for insertion to Woocommerce
                    if (isset($stats['modifications']) && $stats['modifications'] === true) {

                        // Modified products and formats
                        if ($stats['modified_product_ids_count'] > 0 || $stats['modified_format_ids_count'] > 0) {

                            echo '<strong>Modified Products</strong><br/><br/>';

                            if ($stats['modified_product_ids_count'] > 0) {
                                $modified_products = $this->pim->get_response_table_data('products');
                            }
                            if ($stats['modified_format_ids_count'] > 0) {
                                $modified_formats = $this->pim->get_response_table_data('product_formats');
                            }

                            // Loop through the products and add them to Woocommerce
                            if (isset($modified_products)) {

                                $all_mod_prods = $modified_products['modified'];
                                //echo'<pre>';var_dump($connection->get_response_table_data());echo'</pre>';die;
                                $i = 0;
                                foreach ($all_mod_prods as $individual_product) {

                                    $all_formatted_products[$i] = $this->process->process_product($individual_product);
                                    ++$i;
                                }
                            }

                            // Loop through the formats, add them to their product and add all the Woocommerce
                            if (isset($modified_formats)) {

                                $all_mod_formats = $modified_formats['modified'];
                                foreach ($all_mod_formats as $individual_format) {
                                    $new_formatted_products = $this->process->process_format($individual_format, $all_formatted_products);
                                    $all_formatted_products = $new_formatted_products;
                                }
                            }


                            if ($stats['deleted_product_ids_count'] > 0 || $stats['deleted_format_ids_count'] > 0) {
                                echo '<hr>';
                            }
                        }

                        //
                        // Deleted products and formats
                        if ($stats['deleted_product_ids_count'] > 0 || $stats['deleted_format_ids_count'] > 0) {

                            $deleted_ids = $this->pim->get_response_table_deleted_ids();

                            $this->process->process_deletions($stats['deleted_product_ids_count'], $stats['deleted_format_ids_count'], $deleted_ids);

                        }
                        
                    } else {
                        echo '<strong>No changes have been made.</strong>';
                    }
                }
            }
            // Display debug information
            //$connection->print_debbug();
        }
    }
    
    private function get_update_stats()
    {

        // Basic information about the connector and how many modifications and deletions are taking place
        $stats['modified_product_ids_count'] = count($this->pim->get_response_table_modified_ids('products'));
        $stats['modified_format_ids_count'] = count($this->pim->get_response_table_modified_ids('product_formats'));
        $stats['deleted_product_ids_count'] = count($this->pim->get_response_table_deleted_ids('products'));
        $stats['deleted_format_ids_count'] = count($this->pim->get_response_table_deleted_ids('product_formats'));

        // Display version information and number of products being changed
        echo "<h4>Response OK</h4>\n" .
            "<p>" .
            "API version: <b>" . $this->pim->get_response_api_version() . "</b><br />\n" .
            "Action: <b>" . $this->pim->get_response_action() . "</b><br />\n" .
            "Time: <b>" . $this->pim->get_response_time() . "</b> (GMT 0)<br /><br />\n";
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
    
}