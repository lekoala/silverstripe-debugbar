# SilverStripe DebugBar module

[![Build Status](https://travis-ci.org/lekoala/silverstripe-debugbar.svg?branch=master)](https://travis-ci.org/lekoala/silverstripe-debugbar/)
[![scrutinizer](https://scrutinizer-ci.com/g/lekoala/silverstripe-debugbar/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/lekoala/silverstripe-debugbar/)
[![Code coverage](https://codecov.io/gh/lekoala/silverstripe-debugbar/branch/master/graph/badge.svg)](https://codecov.io/gh/lekoala/silverstripe-debugbar)

Use [DebugBar](https://github.com/maximebf/php-debugbar) in SilverStripe. This is only active in Dev mode.

This module will:

- Enable a custom log writer that will log all messages under the messages tab
- Log framework execution based on available hooks
- Log database calls
- Show all debug and debug_request infos from SilverStripe in a tab
- Show all requirements
- Show the current site config
- Show request data based on SilverStripe classes (request parameters, session, cookies)
- Show current locale, framework/cms version, current member

The DebugBar is automatically injected into any html response through the request filter.

#### Managing jQuery

The DebugBar will include its own version of jQuery by default. It will only be disabled
in the admin (which already use jQuery).

If you have added jQuery in your requirements (filename must be jquery.js or jquery.min.js),
the DebugBar will not load its own jQuery version. You can also set the following
configuration flag to false to prevent the DebugBar to include its own jQuery.

```yaml
DebugBar:
  include_jquery: false
```

If you are including jQuery yourself, it is expected to you include it in Page::init().
Below is an example of how to the jquery which ships with the framework:

```php
public function init()
{
    parent::init();
    Requirements::javascript(FRAMEWORK_DIR . '/thirdparty/jquery/jquery.min.js');
}
```

You can force the DebugBar to be disabled by defining:

```php
define('DEBUGBAR_DISABLE', true);
```

## Installation

Since this extension will increase memory footprint, it is recommended to install it only in dev mode

Either run:

```
composer require --dev lekoala/silverstripe-debugbar
```

Or add:

```
"lekoala/silverstripe-debugbar": "1.0.x-dev"
```

to require-dev in your composer.json file

## Options

- `enable_storage`: Store all previous request in the temp folder (enabled by default)
- `auto_debug`: automatically collect debug and debug_request data (disabled by default)
- `ajax`: automatically inject data in ajax requests (disabled by default,
since this makes the chrome request inspector very slow due to the large amount of header data)
- `force_proxy`: always use the database proxy instead of built in PDO collector (enabled by default)
- `check_local_ip`: do not display the DebugBar is not using a local ip (enabled by default)
- `find_source`: trace which file generate the query  (enabled by default)
- `enabled_in_admin`: enable DebugBar in the admin (enabled by default)
- `query_limit`: the number of queries to log (limited to 200 by default for performance reasons)
- `warn_query_limit`: the number of database queries before a warning notice will be send to the Messages tab suggesting performance improvements (100 by default)
- `warn_dbqueries_threshold_seconds`: the threshold for how long a single database query can run before being highlighted as a slow query (default 1 second)
- `warn_request_time_seconds`: the upper limit for a page's total request time which will be marked as dangerously slow (default 5 seconds)
- `warn_warning_ratio`: the ratio from `warn_request_time_seconds` which will define a "notice" alert rather than a dangerous alert (default 0.5, or 2.5 seconds)

## Optimize your queries

The Database tab will show you all queries made by the current request. If you have any
duplicated queries, you will be able to filter them and see them easily to optimize and cache
the data when possible.

To help you in this task, it is recommended to leave the find_source option on. This
will help you to identify what triggers the query and where to setup the cache appropriately.

If you are using `?showqueries=1`, you will also see that the usage has been optimised to display
all queries nicely and their result on the page.

Also remember that if you use the "d" helper (see below), any string variable with "sql" in the name
will be formatted as a sql string.

Slow running queries (see `warn_dbqueries_threshold_seconds`) will be highlighted in the database tab.

## Helpers

The "d" function helps you to quickly debug code. It will use the Symfony VarDumper to display the data.
In a ajax context, it will simply display the data is a simpler fashion.
Called without argument, it will display all objects in the debug backtrace.
It will display the variable name before its content to make it easy to identify data amongst multiple values

```php
d($myvar,$myothervar);
```

Any call to "d" with "sql" in the name of the variable will output a properly formatted sql query, for instance:

```php
d($MyDataList->sql());
```

The "l" function helps you to log messages (and since they will appear in the Messages tab, it is very useful).

```php
l("My message");
```

## A quick note about the Security Page

DebugBarControllerExtension will include for you all the required assets for DebugBar.
This is done "onAfterInit". However, on Security, "onAfterInit" is called before your init()
method in Page.php.
Since you need to add jQuery before DebugBar, this may be a problem, and therefore, requirements
will NOT be included on Security.

If you want DebugBar to work on Security, make sure to include all relevant requirements by
calling DebugBar::includeRequirements(); after you include jQuery. When DebugBar is disabled,
this call will be ignored. Also note that any subsequent call to this method will be ignored
as well.

## Third party

- [SqlFormatted](https://github.com/jdorn/sql-formatter)

## Compatibility

* SilverStripe ^3.2

## Maintainer

LeKoala - thomas@lekoala.be
