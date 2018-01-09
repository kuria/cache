Changelog
#########

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
