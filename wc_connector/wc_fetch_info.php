<?php
require_once 'connections/connections.php';

$connect = new Services_Connection();

$connect->wc_connect();
$woocommerce = $connect->get_wc_connection();

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
