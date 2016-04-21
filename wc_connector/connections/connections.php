<?php

require_once 'settings.php';
require '../../vendor/autoload.php';
use Automattic\WooCommerce\Client;
if (!class_exists('SalesLayer_Updater')) require '../src/SalesLayer-Updater.php';

class Services_Connection
{
    private $pim_connection;
    private $pim_conns;
    private $wc_connection;
    private $db_connection;


    public function get_pim_connection() {
        return $this->pim_connection;
    }

    public function get_pim_conns() {
        return $this->pim_conns;
    }

    public function get_wc_connection() {
        return $this->wc_connection;
    }

    public function get_db_connection() {
        return $this->db_connection;
    }


    public function pim_connect()
    {
        $dbname = DB_NAME;
        $dbhost = DB_HOST;
        $dbusername = DB_USER;
        $dbpassword = DB_PASSWORD;
            
        // Instantiate the Sales Layer class
        $this->pim_connection = new SalesLayer_Updater($dbname, $dbusername, $dbpassword, $dbhost);
    }

    public function pim_conns()
    {
        $this->pim_conns = array(
            '__other_Sales_Layer_connector_code__' => '__other_Sales_Layer_secret__'
            //'__other_Sales_Layer_connector_code__'=>'__other_Sales_Layer_secret__'
        );
    }

    public function wc_connect()
    {
        // Instantiate the Woocommerce class
        // 'WEBSITE_URL', 'CONSUMER_KEY', 'CONSUMER_SECRET'
        $this->wc_connection = new Client(  'WEBSITE_URL',
                                    'CONSUMER_KEY',
                                    'CONSUMER_SECRET');
    }

    public function db_connect()
    {
        // Connect to the DB
        $this->db_connection = new PDO(
                        'mysql:host=' . DB_HOST .
                        ';dbname=' . DB_NAME .
                        ';charset=utf8mb4',
                        DB_USER,
                        DB_PASSWORD,
                        array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
                    );

        // Create the pim_wc_connection table if it doesn't exist
        $create_table =  $this->db_connection->prepare('CREATE TABLE IF NOT EXISTS
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
    }

}