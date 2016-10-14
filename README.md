SilverStripe DebugBar module
==================
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

Please note that jQuery is excluded from vendors and you are expected to include your own jQuery.

You can force the DebugBar to be disabled by defining

    define('DEBUGBAR_DISABLE',true);

Installation
==================

Since this extension will increase memory footprint, it is recommended to install it only in dev mode

Either run:

    composer require lekoala/silverstripe-debugbar --dev

Or add: 

    lekoala/silverstripe-debugbar: "dev-master"

to require-dev in your composer.json file

Options
==================

- enable_storage: Store all previous request in the temp folder (enabled by default)
- auto_debug: automatically collect debug and debug_request data (disabled by default) 
- ajax: automatically inject data in ajax requests (disabled by default, 
since this makes the chrome request inspector very slow due to the large amount of header data)
- force_proxy: always use the database proxy instead of built in PDO collector (enabled by default)
- check_local_ip: do not display the DebugBar is not using a local ip (enabled by default)
- find_source: trace which file generate the query  (enabled by default)
- enabled_in_admin: enable DebugBar in the admin (enabled by default)
- query_limit: the number of queries to log (limited to 200 by default for performance reasons)

Optimize your queries
==================

The Database tab will show you all queries made by the current request. If you have any
duplicated queries, you will be able to filter them and see them easily to optimize and cache
the data when possible.

To help you in this task, it is recommended to leave the find_source option on. This
will help you to identify what triggers the query and where to setup the cache appropriately.

Helpers
==================

The "d" function helps you to quickly debug code. It will use the Symfony VarDumper to display the data.
In a ajax context, it will simply display the data is a simpler fashion.
Called without argument, it will display all objects in the debug backtrace.
It will display the variable name before its content to make it easy to identify data amongst multiple values

    d($myvar,$myothervar);
    
Any call to "d" with "sql" in the name of the variable will output a properly formatted sql query, for instance:

    d($MyDataList->sql());

The "l" function helps you to log messages (and since they will appear in the Messages tab, it is very useful).

    l("My message");

Compatibility
==================
Tested with 3.1+

Maintainer
==================
LeKoala - thomas@lekoala.be