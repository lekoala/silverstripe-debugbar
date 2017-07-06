# Helper methods

## Quick debugging

The `d()` function helps you to quickly debug code. It will use the [Symfony VarDumper](https://github.com/symfony/var-dumper) to display the data in a "pretty" way.

In an XHR/AJAX context, it will simply display the data in a more simple fashion.

When `d()` is called without arguments, it will display all objects in [the debug backtrace](http://php.net/manual/en/function.debug-backtrace.php). It will display the variable name before its content to make it easy to identify data amongst multiple values.

```php
d($myvar, $myothervar);
```

Any call to `d()` with "sql" in the name of the variable will output a properly formatted SQL query, for instance:

```php
d($myDataList->sql());
```

## Quick logging

The `l()` function helps you to log messages, and since they will appear in the "Messages" tab, it is very useful.

```php
l('My message');
```
