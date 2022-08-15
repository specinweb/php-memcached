# php-memcached

// Create Memcached.php instance ...
$memcached = new Client(
'127.0.0.1'
);

$dummy = md5(rand(1111, 9999));

$memcached->set($dummy, 1);
$result = $memcached->get($dummy);

$memcached->delete($dummy);
