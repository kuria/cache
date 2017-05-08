Cache
#####

Caching library with driver abstraction and namespacing support.


.. contents::


Features
********

- driver abstraction
- namespacing support
- built-in driver implementations:

  - Filesystem
  - Memory
  - APC / APCu
  - XCache
  - WinCache
  - Memcache

- extension system

  - implemented using the `kuria/event <https://github.com/kuria/event>`_ library
  - stored and loaded data can be manipulated by extensions

- built-in extensions:

  - ``BoundFileExtension``

    - invalidates cache entries if any one of the bound files is modified
    - useful during development


Requirements
************

- PHP 5.3.0+ or 7.0.0+


Drivers
*******

-  ``FilesystemDriver`` - stores data in the filesystem
-  ``MemoryDriver`` - stores data in the script's memory
-  ``ApcDriver`` - `APC <http://php.net/manual/en/book.apc.php>`__ or `APCu <https://pecl.php.net/package/APCu>`__
-  ``XcacheDriver`` - `XCache <http://xcache.lighttpd.net/>`__
-  ``WinCacheDriver`` - `WinCache <https://pecl.php.net/package/wincache>`__
-  ``MemcacheDriver`` - `Memcache <https://pecl.php.net/package/memcache>`__


Usage
*****

Creating an instance
====================

.. code:: php

   <?php

   use Kuria\Cache\Cache;
   use Kuria\Cache\Driver\MemoryDriver;

   $driver = new MemoryDriver(); // just an example, you can use any other driver
   $cache = new Cache($driver);


Caching a value
===============

The most simple way to cache a value is to use the ``cached()`` method. The passed callback is called only if the value is not found in the cache.

.. code:: php

   <?php

   $value = $cache->cached('foo', function (&$ttl) {
       $ttl = 60; // cache for 1 minute
       $result = some_expensive_function();

       return $result;
   });

This is equivalent to the following:

.. code:: php

   <?php

   $value = $cache->get('foo');

   if (false === $value) {
       $value = some_expensive_function();
       $cache->add('foo', $value, 60); // cache for 1 minute
   }


The API
=======

-  ``has()`` - see if a key exists
-  ``get()`` - get a value for the given key
-  ``getMultiple()`` - get values for multiple keys
-  ``cached()`` - get a value for the given key or populate it using a callback if the key was not found
-  ``add()`` - create a new value (does not overwrite)
-  ``set()`` - set a value (does overwrite)
-  ``increment()`` - increment an integer value
-  ``decrement()`` - decrement an integer value
-  ``remove()`` - remove a key
-  ``clear()`` - remove all keys
-  ``filter()`` - remove keys that begin with the given prefix
-  ``setPrefix()`` - set key prefix (useful if the driver's storage is shared)
-  ``getNamespace()`` - get namespaced part of the cache


Key format
----------

-  only alphanumeric characters, underscores and a dots are allowed
-  the key must begin and end with an alphanumeric character and must not contain consecutive dots


Prefix format
-------------

-  only alphanumeric characters, underscores and a dots are allowed
-  the prefix must begin with an alphanumeric character and must not contain consecutive dots


Allowed data types
------------------

All data types except for the ``resource`` type can be stored in the cache. Objects are stored serialized.

It is not recommended to store ``false`` if you want to be able to determine whether a cached value is returned, as most operations return ``false`` on failure. However it is perfectly valid to do so.


Cache events
============

Possible events emitted by the ``Cache`` class:


``fetch``
---------

-  emitted when a value is being retrieved
-  arguments:

   1. ``array $event``

      -  ``key``: the key being retrieved
      -  ``options``: reference to the options array
      -  ``found``: a boolean value indicating whether the driver returned a value
      -  ``value``: reference to the value returned by the driver (can be ``FALSE`` if not found)

         -  if set to ``FALSE`` and the value was found, the key will be removed from the cache


``store``
---------

-  emitted when a value is being stored
-  arguments:

   1. ``array $event``

      -  ``key``: the key being stored
      -  ``value``: reference to the value being stored
      -  ``ttl``: reference to the TTL
      -  ``options``: reference to the options array


Bound file extension
====================

This extension invalidates cache entries based on modification time of a given list of files.

To set a list of bound files, set the "bound\_files" option when storing a value using ``set()`` or ``add()``.


Registration
------------

.. code:: php

   <?php

   use Kuria\Cache\Extension\BoundFile\BoundFileExtension;

   $extension = new BoundFileExtension();
   $cache->subscribe($extension);

**Warning:** If you remove the extension after it has been used, you will need to clear the cache.


Usage
-----

.. code:: php

   <?php

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
