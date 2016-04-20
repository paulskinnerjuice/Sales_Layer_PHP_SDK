<?php

require_once 'connections/connections.php';

class Custom_Decorator
{

    public function __construct()
    {
        $this->connection = new Services_Connection();

        $this->connection->wc_connect();
        $this->connection->db_connect();
    }

    public function decorate_product_for_wc($individual_product)
    {
        $db = $this->connection->get_db_connection();

        $wc_product['product']['type'] = 'simple';

        $id = $individual_product['id'];
        $getProd = $db->query('SELECT * FROM pim_wc_connection WHERE pim_id = ' . $id . ' LIMIT 1');
        $row_count = $getProd->rowCount();
        if($row_count > 0) {
            $results = $getProd->fetch(PDO::FETCH_ASSOC);

            $pim_img_ids = $results['pim_img_ids'];
            $pim_img_array = explode(',', $pim_img_ids);
        } else {
            $pim_img_array = array();
        }

        // Add all of the PIM information into an array which will then be sent to woocommerce for upload
        $wc_product['product']['title'] = $individual_product['data']['product_name'];
        $wc_product['product']['sku'] = $individual_product['data']['product_reference'];
        $wc_product['product']['description'] = '';
        $wc_product['product']['short_description'] = $individual_product['data']['product_description_en'];

        // Use type_of_product to check the category name against a list of woocommerce categories and get the id
        if(!empty($individual_product['catalogue_id'])) {
            $wc_category_ids = $this->set_pim_to_wc_categories($individual_product['catalogue_id']);
            $wc_product['product']['categories'] = $wc_category_ids;
        }

        // Loop through all of the images
        $i = 0;
        if (!empty($individual_product['data']['product_image'])) {
            $images = $individual_product['data']['product_image'];
            $wc_product['product']['pim_img_ids'] = '';
            foreach ($images as $image) {
                $img_id = array_keys($images);
                if (!in_array($img_id[$i], $pim_img_array)) {
                    $wc_product['product']['images'][$i]['title'] = $img_id[$i];
                    $wc_product['product']['images'][$i]['alt'] = $img_id[$i];
                    $wc_product['product']['images'][$i]['src'] = $image['THM'];
                    $wc_product['product']['images'][$i]['position'] = $i;
                }
                $all_prod_img_ids[$i] = $wc_product['product']['pim_img_ids'] . $img_id[$i];
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
        $wc_product['product']['attributes'][4]['name'] = 'Variations';
        $wc_product['product']['attributes'][4]['variation'] = true;
        if(isset($individual_product['attribute_skus'])){
            $wc_product['product']['attributes'][4]['options'] = $individual_product['attribute_skus'];
        } else {
            $wc_product['product']['attributes'][4]['options'] = array();
        }

        return $wc_product;

    }


    public function add_decorated_product_format_to_product($individual_format, $formatted_product, $format_count)
    {
        // Set the format details
        $formatted_product['product']['type'] = 'variable';

        if (isset($individual_format['wc_var_id'])) {
            $formatted_product['product']['variations'][$format_count]['id'] = $individual_format['wc_var_id'];
        }

        $formatted_product['product']['variations'][$format_count]['sku'] = str_replace(' ', '_', $individual_format['data']['format_reference']);

        $formatted_product['product']['variations'][$format_count]['attributes'][0]['name'] = 'variations';
        $formatted_product['product']['variations'][$format_count]['attributes'][0]['option'] = str_replace(' ', '_', $individual_format['data']['format_reference']);

        if (isset($individual_format['data']['dimensions']) && !empty($individual_format['data']['dimensions'])) {
            $downloads = $individual_format['data']['dimensions'];
            $download_ids = array_keys($downloads);
            $download_id = $download_ids[0];

            $file_path = $individual_format['data']['dimensions'][$download_id]['THM'];

            $formatted_product['product']['variations'][$format_count]['downloadable'] = true;
            $formatted_product['product']['variations'][$format_count]['downloads'][0]['id'] = $download_id;
            $formatted_product['product']['variations'][$format_count]['downloads'][0]['name'] = basename($file_path);
            $formatted_product['product']['variations'][$format_count]['downloads'][0]['file'] = $file_path;
        }
        return $formatted_product;

    }


    public function set_pim_to_wc_categories($input_categorys)
    {
        $category_array = array();

        foreach ($input_categorys as $input_category) {
            $categories = array(
                'pim_cat_id_1' => 'wc_cat_id_1', //Name of category
                'pim_cat_id_2' => 'wc_cat_id_2', //Name of category
                'pim_cat_id_3' => 'wc_cat_id_3', //Name of subcategory
                'pim_cat_id_4' => 'wc_cat_id_4', //Name of subcategory
                'pim_cat_id_5' => 'wc_cat_id_5' //Name of category
            );
            foreach ($categories as $key => $value) {
                if ($input_category == $key) {
                    array_push($category_array, $value);
                }
            }
        }

        return $category_array;
    }
}