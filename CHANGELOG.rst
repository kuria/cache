Changelog
#########

6.0.0
*****

- reintroduced ``PhpFileFormat`` (generates valid PHP files compatible with opcode caches)
- added ``$umask`` parameter to the filesystem driver (defaults to 002)
- PHP 8.1 compatibility


5.0.0
*****

- removed ``PhpFileFormat``
- replaced several helpers with Kuria components


4.0.0
*****

- added PSR-6 cache adapter
- added black hole driver
- added ``&$exists`` argument to ``Cache::get()``
- added ``&$failedKeys`` argument to ``Cache::getMultiple()``
- simplified cache events
- normalized TTL handling
- ``Cache::cached()`` now caches ``NULL`` values as well


3.0.0
*****

- changed most class members from protected to private
- added ``psr/simple-cache-implementation`` provision to composer.json
- cs fixes, added codestyle checks


2.0.0
*****

- major refactoring
- updated to PHP 7.1
- removed key restrictions
- removed obsolete drivers
- implemented redis driver
- implemented memcached driver
- code style improvements


1.0.1
*****

- code style and test improvements


1.0.0
*****

- WinCache driver
- minor improvements
- dependency update
- code style fixes


0.2.1
*****

- Cache - fixed key handling in getMultiple() when prefix is set
- Cache - implemented extension-triggered key removal
- CacheExtensionAbstract - multiple priority configuration
- BoundFileExtension - better default priorities
- MemcacheDriver - better exists() implementation
- FilesystemEntry - do not use machine dependent pack codes


0.2.0
*****

- refactoring
- separated cache and driver code
- better namespacing support (removed mandatory categories)
- removed useless helpers
- cleaned-up the filesystem driver's code


0.1.0
*****

Initial release
