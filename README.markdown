Cache
=====

Caching library with several implementations (Filesystem, APC, XCache, Memache, Memory).


## Features

- shared public API
- several implementations
    - Filesystem
    - APC
    - XCache
    - Memcache
    - Memory
- extensions
    - manipulating stored / loaded data through events


## Requirements

- PHP 5.3 or newer


## Cache implementations


### FilesystemCache

Stores data in the filesystem (in the given cache directory). Uses advisory file locking and/or temporary files to prevent corruption/race conditions.


#### Configuration

There are several configuration directives to be aware of. The defaults should be sensible enough, so feel free to skip this.

- Storage mode
    - `FilesystemCache->setStorageMode()`
        1. `FilesystemCache::STORAGE_PHP` (default, stores data as `.php` files)
        2. `FilesystemCache::STORAGE_NORMAL` (stores data in `.dat` files)
            - if using this mode, be SURE that the cache directory is NOT publicly accessible!
- Temporary files
    - `FilesystemCache->setTemporaryDir()`
        1. `null` - use system's (default)
        2. path to custom temporary directory (might be a good idea in shared environments)
    - `FilesystemCache->setUseTemporaryFiles()`
        1. `true` - use temporary files (default if not on Windows)
        2. `false` - do not use temporary files (default on Windows)
- Removing files
    - `FilesystemCache->setUseUnlink()`
        1. `true` - use unlink for entry removal (default if not on Windows)
        2. `false` - do not use unlink for entry removal (default if on Windows)

Note: If on Windows, be aware of that `clear()` may fail and leave some files behind if performed on live cache.


### ApcCache

Uses [APC](http://php.net/manual/en/book.apc.php).


### XCacheCache

Uses [XCache](http://xcache.lighttpd.net/).


### MemcacheCache

Uses [Memcache](http://php.net/manual/en/book.memcache.php).


Note: Memcache does not support `clear($category)`, only `clear()`. This is because it is not possible
to reliably get list of keys that exist in the cache.


### MemoryCache

Stores all the data in an array in the script's memory, so the cache always starts empty and is cleared after the script exits. (And it is private to the current script, not shared.)


## Usage example


### Creating an instance

Let's use the `MemoryCache` implementation in the examples (it requires no extensions or setup).

Other implementations might have some constructor arguments.

    use Kuria\Cache\Provider\MemoryCache;

    $cache = new MemoryCache();


### The API

See `CacheInterface` for list of all methods that are shared across cache implementations.

- `has()` - see if an entry exists
- `get()` - load an entry
- `add()` - create an entry (does not overwrite)
- `set()` - create an entry (does overwrite)
- `increment()` - increment an integer value
- `decrement()` - decrement an integer value
- `remove()` - remove an entry
- `clear()` - clear the entire cache or the given category

Most of the public API methods require at least 2 arguments:

1. `$category` - name of the cache category (you can consider this a "folder name")
2. `$name` - name of the entry (you can consider this a "file name")

Note: Both `$category` and `$name` should consist of alphanumeric characters and underscores only.

    $cache->set('mycategory', 'foo', 'bar');

    var_dump($cache->get('mycategory', 'foo'));
    // should print: string(3) "bar"
    // if everything went ok


#### Allowed data types

All data types except for the `resource` type can be stored in the cache. Objects are stored serialized.


### Local cache

You have to pass both `$category` and `$name` arguments to most methods. You probably don't want to
repeat the category name over and over again in your code (unless it is a really short one).

The solution to this is using a "local cache" instance. Local cache is essentialy
a wrapper around the original cache with the `$category` argument being set automatically.
It provides almost identical API as `CacheInterface`, except the `$category` argument is missing.

See `LocalCacheInterface` for list of all available methods.

To create an instance of local cache, simply call `getLocal($category)` on the cache instance:

    $local = $cache->getLocal('mycategory');

Now there is no need to specify the category for every operation:

    $local->set('result', 123);

    var_dump($local->get('result'));
    // should print: int(123)
    // if everything went ok


### Handling failure correctly

All of the public API functions may return `false` in case of failure. It is important
to handle these failures correctly.

From the point of view of the running PHP script, the cache is an external system that
can be accessed and altered by many other threads / processes at the same time.

- it is not safe to assume that `get()` cannot fail (return `false`) because `has()` has succeeded
- it is not safe to assume that `get()` cannot fail (return `false`) because `set()` has succeeded

Other threads may alter the data even between 2 method calls.

For example, this is **wrong**:

    // lets assume "foo" is an array

    if ($cache->has('mycategory', 'foo')) {
        $foo = $cache->get('mycategory', 'foo');
        echo $foo['index']; // oops.. $foo might be false
    }

This is **better**:

    if ($cache->has('mycategory', 'foo')) {
        $foo = $cache->get('mycategory', 'foo');
        if (false !== $foo) {
            echo $foo['index'];
        }
    }

This is **even better**:

    $foo = $cache->get('mycategory', 'foo');
    if (false !== $foo) {
        echo $foo['index'];
    }


#### No cache

You can use the `NoCache` implementation to verify that your code is handling failures correctly.

This implementation does not cache anything - instead it reports failure (returns `false`) for most API methods
that work with cached entries.


## Cache events

Stored / loaded data can be additionally manipulated and validated by extensions through events.
This is implemented using the [kuria/event](https://github.com/kuria/event) library.

The `Cache` extends `ExternalObservable`. This means:

- you can attach observers directly to the cache instance
- you can replace the underlying observable instance by using `Cache->setNestedObservable()`


### Event list

The cache emits the following events:

- `CacheFetchEvent` (`CacheFetchEvent::NAME`)
    - emitted when an entry is being loaded using `get()`
- `CacheStoreEvent` (`CacheStoreEvent::NAME`)
    - emitted when an entry is being stored using `set()` or `add()`

Both event classes expose information about the operation through public properties. Some of these
are references that can be used to manipulate the data.


### Bound file extension

This extension invalidates cache entries based on modification time of a given list of files.
Useful during development. The check can be disabled in production.

This extension is triggered through usage of the `$options` parameter of `get()`, `set()` and `add()` methods.

- `get()` method requires the option `"has_bound_files" => true` to perform the validation
- `set()` and `add()` methods require the option `"bound_files" => array()` to map and store a list of bound files


#### Registration

    use Kuria\Cache\Extension\BoundFileExtension;

    $boundFileExtension = new BoundFileExtension();
    $boundFileExtension->setVerifyBoundFiles(true); // FALSE may be a good idea in production

    $cache->addSubscriber($boundFileExtension);


#### Usage

    // getting an entry that contains bound files
    // FALSE is returned if the bound file validation fails
    $cache->get('foo', 'bar', 0, array(
        'has_bound_files' => true,
    ));

    // storing an entry with bound files using set() or add()
    $cache->set('foo', 'bar', 0, array(
        'bound_files' => array(
            'path/to/file1',
            'path/to/file2',
            // ...
        ),
    );
