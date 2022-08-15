<?php

use Memcached\Client;


// Create Memcached.php instance ...
$memcached = new Client(
    '127.0.0.1'
);

// Some setup for randomized key(s) for demonstration ...
srand(microtime(true));
$dummy = md5(rand(1111, 9999));

// Try to do some stuff with memcached instance ...
try {

    $memcached->set($dummy, 1);
    $result = $memcached->get($dummy);

    $memcached->delete($dummy);

} catch (Exception $e) {
    $result = $e->getMessage();

}

echo '<pre>';
echo '<h1>Simple Demonstration</h1>';
echo 'Result should be "5":<br />';
echo $result;
echo '</pre>';
