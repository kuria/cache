Cache
#####

Caching library with driver abstraction.

.. contents::


Features
********

- entry operations: has, add, set, get, delete
- multiple-entry operations: getting, setting, adding, deleting
- listing, filtering, cleanup (requires driver support)
- TTL expiration
- key prefixing
- stored and retrieved values can be manipulated via events
- `PSR-16 <http://www.php-fig.org/psr/psr-16/>`_ simple cache wrapper
- multiple built-in driver implementations


Requirements
************

- PHP 7.1+


Built-in drivers
****************

==================== ========== =========== ============ ========== ======= ==========================================================
Driver               Multi-read Multi-write Multi-delete Filterable Cleanup Required extension
==================== ========== =========== ============ ========== ======= ==========================================================
``FilesystemDriver`` no         no          no           yes        yes     none
``ApcuDriver``       yes        yes         yes          yes        no      `APCu <http://php.net/manual/en/book.apcu.php>`_
``MemcachedDriver``  yes        partial     yes          no         no      `Memcached <http://php.net/manual/en/book.memcached.php>`_
``RedisDriver``      yes        yes         yes          yes        no      `PhpRedis <https://github.com/phpredis/phpredis>`_
``MemoryDriver``     yes        yes         yes          yes        yes     none
==================== ========== =========== ============ ========== ======= ==========================================================

.. NOTE::

   The cache will emulate multi-read/write/delete if the driver doesn't support it natively.

.. NOTE::

   Missing cleanup support means that it is managed automatically by the service.


Usage
*****

Creating a cache instance
=========================

.. code:: php

   <?php

   use Kuria\Cache\Cache;
   use Kuria\Cache\Driver\Filesystem\FilesystemDriver;

   // create a driver
   // (you can use any other implementation)
   $driver = new FilesystemDriver(__DIR__ . '/cache');

   $cache = new Cache($driver);


``setPrefix()`` - configure cache prefix
========================================

The ``setPefix()`` method defines a prefix that will be applied to all keys before
they are passed to the underlying driver implementation.

The prefix can be an empty string to disable this functionality.

.. code:: php

   <?php

   $cache->setPrefix('prefix_');


``getNamespace()`` - get a namespaced cache instance
====================================================

The ``getNamespace()`` method returns a cache instance that applies a prefix to all
keys before passing them to the original cache.

.. code:: php

   <?php

   $fooCache = $cache->getNamespace('foo.');

   $fooCache->get('bar'); // reads foo.bar
   $fooCache->delete('baz'); // deletes foo.baz
   $fooCache->clear(); // deletes foo.* (if the cache is filterable)
   // etc.


``has()`` - check if an entry exists
====================================

The ``has()`` method returns ``TRUE`` or ``FALSE`` indicating whether the
entry exists or not.

.. code:: php

   <?php

   if ($cache->has('key')) {
       echo 'Entry exist';
   } else {
       echo 'Entry does not exist';
   }

.. WARNING::

   Beware of a possible race-condition between calls to ``has()`` and ``get()``.

   If possible, only call ``get()`` and check for a ``NULL`` result.


``get()`` - read a single entry
===============================

The ``get()`` method returns the stored value or ``NULL`` if the entry does not exist.

.. code:: php

   <?php

   $value = $cache->get('key');


``getMultiple()`` - read multiple entries
=========================================

The ``getMultiple()`` method returns a key-value map. Nonexistent keys will have
a ``NULL`` value.

.. code:: php

   <?php

   $values = $cache->getMultiple(['foo', 'bar', 'baz']);


``listKeys()`` - list keys in the cache
=======================================

The ``listKeys()`` method will return an iterable list of keys in the cache, optionally
matching a common prefix.

If the driver doesn't support this operation, an ``UnsupportedOperationException``
exception will be thrown. You can check support using the ``isFilterable()`` method.

.. code:: php

   <?php

   if ($cache->isFilterable()) {
       // list all keys
       foreach ($cache->listKeys() as $key) {
           echo "{$key}\n";
       }

       // list keys beginning with foo_
       foreach ($cache->listKeys('foo_') as $key) {
           echo "{$key}\n";
       }
   }


``getIterator()`` - list keys and values in the cache
=====================================================

The ``getIterator()`` method will return an iterator for all keys and values in the
cache. This is a part of the ``IteratorAggregate`` interface.

If the driver doesn't support this operation, an ``UnsupportedOperationException``
exception will be thrown. You can check support using the ``isFilterable()`` method.

Listing all keys and values:

.. code:: php

   <?php

   foreach ($cache as $key => $value) {
       echo $key, ': ';
       var_dump($value);
   }

Listing keys and values matching a prefix:

.. code:: php

   <?php

   foreach ($cache->getIterator('foo_') as $key => $value) {
       echo $key, ': ';
       var_dump($value);
   }


``add()`` / ``set()`` - create a new entry
==========================================

The ``add()`` and ``set()`` methods both create an entry in the cache.

The ``set()`` method will overwrite an existing entry, but ``add()`` will not.

.. code:: php

   <?php

   $cache->add('foo', 'foo-value');

   $cache->set('bar', 'bar-value');

TTL (time-to-live in seconds) can be specified using the third argument:

.. code:: php

   <?php

   $cache->set('foo', 'foo-value', 60);

   $cache->add('bar', 'bar-value', 120);

See `Allowed value types`_.


``addMultiple()`` / ``setMultiple()`` - create multiple entries
===============================================================

The ``addMultiple()`` and ``setMultiple()`` methods both create multiple entries
in the cache.

The ``setMultiple()`` method will overwrite any existing entries with the same keys,
but ``addMultiple()`` will not.

.. code:: php

   <?php

   $cache->addMultiple(['foo' => 'foo-value', 'bar' => 'bar-value']);

   $cache->setMultiple(['foo' => 'foo-value', 'bar' => 'bar-value']);

TTL (time-to-live in seconds) can be specified using the second argument:

.. code:: php

   <?php

   $cache->addMultiple(['foo' => 'foo-value', 'bar' => 'bar-value'], 60);

   $cache->setMultiple(['foo' => 'foo-value', 'bar' => 'bar-value'], 120);

See `Allowed value types`_.


``cached()`` - cache the result of a callback
=============================================

The ``cached()`` method tries to read a value from the cache. If it does not exist,
it invokes the given callback and caches its result.

.. code:: php

   <?php

   $value = $cache->cached('key', 60, function () {
       // some expensive operation
       $result = 123;

       return $result;
   });


``delete()`` - delete an entry
==============================

The ``delete()`` method deletes a single entry from the cache.

.. code:: php

   <?php

   if ($cache->delete('key')) {
       echo 'Entry deleted';
   }


``deleteMultiple()`` - delete multiple entries
==============================================

The ``deleteMultiple()`` method deletes multiple entries from the cache.

.. code:: php

   <?php

   if ($cache->deleteMultiple(['foo', 'bar', 'baz'])) {
       echo 'All entries deleted';
   } else {
       echo 'One or more entries could not be deleted';
   }


``filter()`` - delete entries using a prefix
============================================

The ``filter()`` method deletes all entries that match the given prefix.

If the driver doesn't support this operation, an ``UnsupportedOperationException``
exception will be thrown. You can check support using the ``isFilterable()`` method.

.. code:: php

   <?php

   if ($cache->isFilterable()) {
       $cache->filter('foo_');
   }


``clear()`` - delete all entries
================================

The ``clear()`` method deletes all entries.

If a cache prefix is set and the cache is filterable, only entries matching
that prefix will be cleared.

.. code:: php

   <?php

   $cache->clear();


``cleanup()`` - clean-up the cache
==================================

Some cache drivers (e.g. ``FilesystemDriver``) support explicit triggering of the cleanup
procedures (removal of expired entries etc).

If the driver doesn't support this operation, an ``UnsupportedOperationException``
exception will be thrown. You can check support using the ``supportsCleanup()`` method.

.. code:: php

   <?php

   if ($cache->supportsCleanup()) {
       $cache->cleanup();
   }


Allowed value types
*******************

All types except for the resource type can be stored in the cache. Most drivers
use standard `object serialization <http://php.net/manual/en/language.oop5.serialization.php>`_.

It is not recommended to store ``NULL`` if you want to be able distinguish between
a nonexistent entry and a ``NULL`` value.


Cache events
************

``CacheEvents::READ``
=====================

Emitted when an entry has been read.

The listener is passed a single ``CacheEvent`` instance. Its ``value`` property can be
manipulated.

.. code:: php

   <?php

   use Kuria\Cache\CacheEvent;
   use Kuria\Cache\CacheEvents;

   $cache->on(CacheEvents::READ, function (CacheEvent $e) {
       echo "Reading {$e->key}\n";
   });


``CacheEvents::WRITE``
======================

Emitted when an entry is about to be written.

The listener is passed a single ``CacheEvent`` instance. Its ``value`` property can be
manipulated.

.. code:: php

   <?php

   use Kuria\Cache\CacheEvent;
   use Kuria\Cache\CacheEvents;

   $cache->on(CacheEvents::WRITE, function (CacheEvent $e) {
       echo "Writing {$e->key}\n";
   });


``CacheEvents::DRIVER_EXCEPTION``
=================================

Emitted when the underlying driver implementation throws an exception.

The listener is passed the exception object. This is useful for debugging / logging
purposes.

.. code:: php

   <?php

   use Kuria\Cache\CacheEvent;
   use Kuria\Cache\CacheEvents;

   $cache->on(CacheEvents::DRIVER_EXCEPTION, function (\Throwable $e) {
       echo 'Driver exception: ', $e;
   });


PSR-16: Simple cache wrapper
****************************

The ``SimpleCache`` class is a wrapper implementing ``Psr\SimpleCache\CacheInterface``.

To use it, you need to have ``psr/simple-cache`` (``^1.0``) installed.

See http://www.php-fig.org/psr/psr-16/

.. code:: php

   <?php

   use Kuria\Cache\Psr\SimpleCache;

   $simpleCache = new SimpleCache($cache);
