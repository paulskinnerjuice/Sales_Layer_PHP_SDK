<?php

require_once 'pim_wc_process.php';

$connection = new Pim_Wc_Process();
$connection->run_updater();