<?php

require_once 'connections/connections.php';
require_once 'decorations/custom_decorations.php';

class Process_Products
{

    public function __construct()
    {
        $connection = new Services_Connection();
        $this->decorate = new Custom_Decorations();

        $this->db = $connection->db_connect();

        $this->wc = $connection->wc_connect();
    }

    public function process_product($individual_product)
    {
        // Format data to accommodate woocommerce
        $individual_formatted_product = $this->decorate->decorate_product_for_wc($individual_product);

        $pim_id = $individual_product['id'];

        if (isset($individual_formatted_product['product']['pim_img_ids'])) {
            $pim_img_ids = $individual_formatted_product['product']['pim_img_ids'];
        }


        // Check if product already exists and either run a Create or Update
        $check_prod = $this->db->query('SELECT * FROM pim_wc_connection WHERE pim_id = ' . $pim_id . ' LIMIT 1');
        $row_count = $check_prod->rowCount();
        if ($row_count > 0) {
            $result = $check_prod->fetch(PDO::FETCH_ASSOC);
            $process_type = 'Updated';
            $wc_id = $result['wc_id'];

            // Modify in Woocommerce
            //echo'<pre>';var_dump($individual_formatted_product);echo'</pre><br/><br/>';
            $return_data = $this->wc->put('products/' . $wc_id, $individual_formatted_product);

            $individual_formatted_product['product']['id'] = $wc_id;

            if (isset($pim_img_ids)) {
                $all_wc_images = $return_data['product']['images'];
                $wc_img_ids = '';
                foreach ($all_wc_images as $wc_image) {
                    $wc_img_ids = $wc_img_ids . $wc_image['id'] . ',';
                }
            }

            // Add to the database
            if (isset($wc_img_ids)) {
                $update_product = $this->db->prepare('UPDATE pim_wc_connection SET pim_img_ids=?, wc_img_ids=? WHERE pim_id=?');
                $update_product->execute(array($pim_img_ids, $wc_img_ids, $pim_id));
            }

        } else {
            $process_type = 'Created';

            // On rare occasions Woocommerce will leave as sku in the wc_postmeta table, making it impossible to add this sku again
            // Here we check for the sku which we are creating and, if it exists, delete it
            $sku_id = $individual_formatted_product['product']['sku'];
            $check_wc_sku = $this->db->query('SELECT * FROM wp_postmeta WHERE meta_key = "_sku" AND meta_value = "' . $sku_id . '"');
            $row_count = $check_wc_sku->rowCount();
            if ($row_count == 0) {
                $delete_sku = $this->db->prepare('DELETE FROM wp_postmeta WHERE meta_key = "_sku" AND meta_value = ? LIMIT 1');
                $delete_sku->execute(array($sku_id));
            }

            // Create in Woocommerce
            $return_data = $this->wc->post('products', $individual_formatted_product);
            $wc_id = $return_data['product']['id'];
            $individual_formatted_product['product']['id'] = $wc_id;

            if (isset($pim_img_ids)) {
                $all_wc_images = $return_data['product']['images'];
                $wc_img_ids = '';
                foreach ($all_wc_images as $wc_image) {
                    $wc_img_ids = $wc_img_ids . $wc_image['id'] . ',';
                }
            }

            // Create in pim_wc_connection
            if (isset($wc_img_ids)) {
                $create_product = $this->db->prepare('INSERT INTO pim_wc_connection(pim_id, pim_img_ids, wc_id, wc_img_ids) VALUES(?,?,?,?)');
                $create_product->execute(array($pim_id, $pim_img_ids, $wc_id, $wc_img_ids));
            } else {
                $create_product = $this->db->prepare('INSERT INTO pim_wc_connection(pim_id, wc_id) VALUES(?,?)');
                $create_product->execute(array($pim_id, $wc_id));
            }
        }

        // Print the product on the screen
        echo $process_type . ' in WC: ' . $individual_formatted_product['product']['title'] . '<br/>';
        echo $pim_id . ' - ' . $wc_id;
        echo '</br></br>';

        Return $individual_formatted_product;
    }


    public function process_format($individual_format, $all_formatted_products)
    {
        $pim_var_id = $individual_format['id'];
        foreach ($all_formatted_products as $key => $formatted_product) {

            $wc_id = $formatted_product['product']['id'];

            $get_pim_id = $this->db->query('SELECT * FROM pim_wc_connection WHERE wc_id = ' . $wc_id . ' LIMIT 1');
            $result = $get_pim_id->fetch(PDO::FETCH_ASSOC);

            $pim_id = $result['pim_id'];

            // Loop through all the products and find the one that applies to this format                 
            if ($pim_id == $individual_format['products_id']) {

                $key_to_change = $key;

                $get_wc_var_id = $this->db->query('SELECT * FROM pim_wc_connection WHERE pim_var_id = ' . $pim_var_id . ' LIMIT 1');
                $result = $get_wc_var_id->fetch(PDO::FETCH_ASSOC);
                if (count($result) > 0) {
                    $individual_format['wc_var_id'] = $result['wc_var_id'];
                }

                $prod_pim_id = $pim_id;
                $prod_wc_id = $wc_id;

                $attr_count = count($formatted_product['product']['attributes']);
                if (isset($formatted_product['product']['variations']) && !empty($formatted_product['product']['variations'])) {
                    $format_count = count($formatted_product['product']['variations']);
                } else {
                    $format_count = 0;
                }
                $finished_format = $this->decorate->add_decorated_product_format_to_product($individual_format, $formatted_product, $attr_count, $format_count);

            }
        }
        // Update this product in the parent product with the new format to be returned below
        $all_formatted_products[$key_to_change]['product'] = $finished_format['product'];

        // As with products, we want to check that the sku for the format we're creating hasn't been left in wp_postmeta - but only if this is a new format
        $check_pim_var = $this->db->query('SELECT * FROM pim_wc_connection WHERE pim_var_id = ' . $pim_var_id);
        $row_count = $check_pim_var->rowCount();
        if ($row_count == 0) {

            $format_sku = $finished_format['product']['variations'][$format_count]['sku'];
            $check_wc_sku = $this->db->query('SELECT * FROM wp_postmeta WHERE meta_key = "_sku" AND meta_value = "' . $format_sku . '"');
            $row_count = $check_wc_sku->rowCount();

            if ($row_count > 0) {
                $delete_sku = $this->db->prepare('DELETE FROM wp_postmeta WHERE meta_key = "_sku" AND meta_value = ? LIMIT 1');
                $delete_sku->execute(array($format_sku));
            }
        }

        // Push this into woocommerce and return the id of the format
        $return_data = $this->wc->put('products/' . $prod_wc_id, $finished_format);

        // Get the WC var id and set it within itself so it's not going to conflict in later iterations of this loop
        $wc_var_id = $return_data['product']['variations'][$format_count]['id'];
        $all_formatted_products[$key_to_change]['product']['variations'][$format_count]['id'] = $wc_var_id;

        // Check if the var already exists in the db and, if not; add it
        $check_format = $this->db->query('SELECT * FROM pim_wc_connection WHERE pim_var_id = ' . $pim_var_id . ' LIMIT 1');
        $row_count = $check_format->rowCount();
        if ($row_count == 0) {
            $create_format = $this->db->prepare('INSERT INTO pim_wc_connection(pim_id, pim_var_id, wc_id, wc_var_id) values(?,?,?,?)');
            $create_format->execute(array($prod_pim_id, $pim_var_id, $prod_wc_id, $wc_var_id));
        }

        // Return the formatted product so it can be used correctly in the next iteration of the loop
        return $all_formatted_products;

    }


    public function process_deletions($deleted_product_ids_count, $deleted_format_ids_count, $deleted_ids)
    {

        if ($deleted_product_ids_count > 0) {
            // Delete products
            $deleted_product_ids = $deleted_ids['products'];

            echo '<strong>Deleted Products</strong><br/>';

            foreach ($deleted_product_ids as $deleted_product_id) {

                $delete_type = 'product';
                $wc_id = $this->get_deleted_id_and_delete($delete_type, $deleted_product_id);

                echo $deleted_product_id . ' - ' . $wc_id . '<br/>';
            }
            echo '<br/>';
        }
        if ($deleted_format_ids_count > 0) {
            // Delete formats
            $deleted_format_ids = $deleted_ids['product_formats'];

            echo '<strong>Deleted Formats</strong><br/>';

            foreach ($deleted_format_ids as $deleted_format_id) {

                $delete_type = 'format';
                $wc_id = get_deleted_id_and_delete($delete_type, $deleted_format_id);

                echo $deleted_format_id . ' - ' . $wc_id . '<br/>';
            }
        }
    }


    public function get_deleted_id_and_delete($delete_type, $deleted_id)
    {
        // Set the type of id to search for
        if ($delete_type == 'product') {
            $id_type = 'pim_id';
        } elseif ($delete_type == 'format') {
            $id_type = 'pim_var_id';
        }

        // Search for the WC version of the id depending on above, and put it into the wc_id variable
        $delete_id = $this->db->query('SELECT * FROM pim_wc_connection WHERE ' . $id_type . ' = ' . $deleted_id . ' LIMIT 1');
        $result = $delete_id->fetch(PDO::FETCH_ASSOC);
        if ($delete_type == 'product') {
            $wc_id = $result['wc_id'];
        } elseif ($delete_type == 'format') {
            $wc_id = $result['wc_var_id'];
        }

        // Delete from the database
        $delete_from_db = $this->db->prepare('DELETE FROM pim_wc_connection WHERE ' . $id_type . ' = ' . $deleted_id . '');
        $delete_from_db->execute();

        // Delete from Woocommerce
        $this->wc->delete('products/' . $wc_id, ['force' => true]);

        return $wc_id;
    }
}