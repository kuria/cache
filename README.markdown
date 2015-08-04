Cache
=====

Caching library with driver abstraction and namespacing support.


## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Drivers](#drivers)
- [Usage example](#usage)
- [Cache events](#events)
- [Bound file extension](#bound-file-extension)

## <a name="features"></a> Features

- driver abstraction
- namespacing support
- built-in driver implementations:
    - Filesystem
    - Memory
    - APC / APCu
    - XCache
    - Memcache
- extension system
    - implemented using the [kuria/event](https://github.com/kuria/event) library
    - stored and loaded data can be manipulated by extensions
- built-in extensions:
    - BoundFileExtension (invalidates cache entries if any one of the bound files is modified)
        - useful during development

## <a name="requirements"></a> Requirements

- PHP 5.3 or newer


## <a name="drivers"></a> Drivers

- `FilesystemDriver`
    - stores data in the filesystem (in the given cache directory)
    - uses advisory file locking and/or temporary files to prevent race conditions
- `MemoryDriver`
    - stores data in the script's memory
- `ApcDiver`
    - uses [APC](http://php.net/manual/en/book.apc.php) or [APCu](https://pecl.php.net/package/APCu)
- `XcacheDriver`
    - uses [XCache](http://xcache.lighttpd.net/)
- `MemcacheDriver`
    - uses [Memcache](https://pecl.php.net/package/memcache)


## <a name="usage"></a> Usage example


### Creating an instance

    use Kuria\Cache\Cache;
    use Kuria\Cache\Driver\MemoryDriver;

    $driver = new MemoryDriver(); // just an example, you can use any other driver
    $cache = new Cache($driver);


### Caching a value

The most simple way to cache a value is to use the `cached()` method. The passed callback is called
only if the value is not found in the cache.

    $value = $cache->cached('foo', function (&$ttl) {
        $ttl = 60; // cache for 1 minute
        $result = some_expensive_function();

        return $result;
    });

This is equivalent to the following:

    $value = $cache->get('foo');

    if (false === $value) {
        $value = some_expensive_function();
        $cache->add('foo', $value, 60); // cache for 1 minute
    }


### The API

- `has()` - see if a key exists
- `get()` - get a value for the given key
- `getMultiple()` - get values for multiple keys
- `cached()` - get a value for the given key or populate it using a callback if the key was not found
- `add()` - create a new value (does not overwrite)
- `set()` - set a value (does overwrite)
- `increment()` - increment an integer value
- `decrement()` - decrement an integer value
- `remove()` - remove a key
- `clear()` - remove all keys
- `filter()` - remove keys that begin with the given prefix
- `setPrefix()` - set key prefix (useful if the driver's storage is shared)
- `getNamespace()` - get namespaced part of the cache


#### Key format

- only alphanumeric characters, underscores and a dots are allowed
- the key must begin and end with an alphanumeric character and must not contain consecutive dots


#### Prefix format

- only alphanumeric characters, underscores and a dots are allowed
- the prefix must begin with an alphanumeric character and must not contain consecutive dots


#### Allowed data types

All data types except for the `resource` type can be stored in the cache. Objects are stored serialized.


### <a name="events"></a> Cache events

Possible events emitted by the `Cache` class:

#### fetch

- emitted when a value is being retrieved
- arguments:
    1. `array $event`
        - `key`: the key being retrieved
        - `options`: reference to the options array
        - `value`: reference to the value returned by the driver (can be `FALSE`)


#### store

- emitted when a value is being stored
- arguments:
    1. `array $event`
        - `key`: the key being stored
        - `value`: reference to the value being stored
        - `ttl`: reference to the TTL
        - `options`: reference to the options array


### <a name="bound-file-extension"></a> Bound file extension

This extension invalidates cache entries based on modification time of a given list of files.

To set a list of bound files, set the "bound_files" option when storing a value using `set()`
or `add()`.


#### Registration

    use Kuria\Cache\Extension\BoundFile\BoundFileExtension;
    
    $extension = new BoundFileExtension();
    $cache->subscribe($extension);


#### Usage

    // storing a value with bound files using set() or add()
    $cache->set('foo', 0, array(
        'bound_files' => array(
            'path/to/file1',
            'path/to/file2',
            // ...
        ),
    );

    // to get a value that contains bound files, just call get() as you would normally
    // if any of the bound files were modified, FALSE will be returned
    $value = $cache->get('foo');
