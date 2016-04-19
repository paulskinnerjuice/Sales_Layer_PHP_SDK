<?php
require_once 'settings.php';
require '../../vendor/autoload.php';
use Automattic\WooCommerce\Client;
	
$woocommerce = new Client('WEBSITE_URL', 'CONSUMER_KEY', 'CONSUMER_SECRET');

// View all product information
/*
echo '<pre>';
var_dump($woocommerce->get('products'));
echo '</pre>';
*/

// View specific product information
/*
echo '<pre>';
var_dump($woocommerce->get('products/xxxx'));
echo '</pre>';
*/

// View a count of all products
/*
echo '<pre>';
var_dump($woocommerce->get('products/count'));
echo '</pre>';
*/

// View all product category information
/*
echo '<pre>';
var_dump($woocommerce->get('products/categories'));
echo '</pre>';
*/

// View a single product category
/*
echo '<pre>';
var_dump($woocommerce->get('products/categories/x'));
echo '</pre>';
*/
