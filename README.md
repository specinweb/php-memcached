# php-memcached

**Create Memcached.php instance**

$memcached = new Client(
'127.0.0.1'
);

**Random string**

`$dummy = md5(rand(1111, 9999));`

**Set**

`$memcached->set($dummy, 1);`

 **Get**

`$result = $memcached->get($dummy);`

**Delete**

`$memcached->delete($dummy);`
