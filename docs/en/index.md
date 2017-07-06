# SilverStripe Debug Bar documentation

SilverStripe Debug Bar is a wrapper for [PHP DebugBar](http://phpdebugbar.com) which integrates with SilverStripe to provide more useful information about your projects. The Debug Bar can help you to easily identify performance issues, analyse environment settings and discover which parts of your code are being used.

For example, if your application is running the same database query multiple times in a loop, or a certain controller action is taking a long time to run, Debug Bar will highlight these bottlenecks so you can take steps to improve your overall site performance.

## Installation

You can install the Debug Bar module with Composer:

```
composer require lekoala/silverstripe-debugbar
```

The module will be enabled by default (see [configuration](configuration.md)) on every page in your site if you have developer mode enabled. It will not run outside of a local development environment for security reasons. You can access the debug bar by clicking on the SilverStripe logo in the bottom left corner, or one of the tabs at the bottom if it is visible already to expand it.

The DebugBar is automatically injected into any HTML response through the `DebugBarRequestFilter`, and will only run in "dev" mode.


## Contents

* [Index](index.md)
* [Execution timeline](timeline.md)
* [Database profiling](database.md)
* [System logs and messages](messages.md)
* [Template use](templates.md)
* [Environment and other information](other.md)
* [Helper methods](helpers.md)
* [Configuration options](configuration.md)
* [Troubleshooting](troubleshooting.md)
