<?php
/**
 * Created by PhpStorm.
 * User: juicecreative
 * Date: 19/04/2016
 * Time: 10:48
 */
require_once 'pim_wc_process.php';

$connection = new Pim_Wc_Process();
$connection->run_updater();