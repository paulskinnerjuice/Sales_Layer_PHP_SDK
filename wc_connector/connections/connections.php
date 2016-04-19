<?php

require_once 'settings.php';
require '../../vendor/autoload.php';
use Automattic\WooCommerce\Client;
if (!class_exists('SalesLayer_Updater')) require '../src/SalesLayer-Updater.php';

class Services_Connection
{

    public function pim_connect()
    {
        $dbname = DB_NAME;
        $dbhost = DB_HOST;
        $dbusername = DB_USER;
        $dbpassword = DB_PASSWORD;

        $CONNS = array(
            'CN1133H4648C607' => '6919b2fd1b48ef9018ea1e1d272fed6b'
            //'__other_Sales_Layer_connector_code__'=>'__other_Sales_Layer_secret__'
        );

        // Instantiate the Sales Layer class
        $pim = new SalesLayer_Updater($dbname, $dbusername, $dbpassword, $dbhost);
        
        $return_data['pim'] = $pim;
        $return_data['CONNS'] = $CONNS;
        
        return $return_data;
    }

    public function wc_connect()
    {
        // Instantiate the Woocommerce class
        // 'WEBSITE_URL', 'CONSUMER_KEY', 'CONSUMER_SECRET'
        $woocommerce = new Client(  'http://dev/paul/mayfield-test/',
                                    'ck_aa0ea0a3a409e8e5afdf433e1b85273d9417bb9e',
                                    'cs_b6d19ff8becaabdad45758bc4da85d277ea605b5');
        
        return $woocommerce;
    }

    public function db_connect()
    {
        // Connect to the DB
        $db = new PDO(
                        'mysql:host=' . DB_HOST .
                        ';dbname=' . DB_NAME .
                        ';charset=utf8mb4',
                        DB_USER,
                        DB_PASSWORD,
                        array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
                    );

        // Create the pim_wc_connection table if it doesn't exist
        $create_table =  $db->prepare('CREATE TABLE IF NOT EXISTS
                                          pim_wc_connection(
                                              id int(11) NOT NULL AUTO_INCREMENT,
                                              pim_id int(11) NOT NULL,
                                              pim_var_id int(11) NOT NULL,
                                              pim_img_ids text DEFAULT NULL,
                                              wc_id int(11),
                                              wc_var_id int(11) NOT NULL,
                                              wc_img_ids text DEFAULT NULL,
                                              PRIMARY KEY (id)
                                          )
                                          engine=InnoDB'
                                        );
        $create_table->execute();

        return $db;
    }

}