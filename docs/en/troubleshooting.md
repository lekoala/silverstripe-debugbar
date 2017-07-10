# Troubleshooting

## Managing jQuery

The DebugBar will include its own version of jQuery by default. It will only be disabled
in the admin (which already use jQuery).

If you have added jQuery in your requirements (filename must be jquery.js or jquery.min.js),
the DebugBar will not load its own jQuery version. You can also set the following
configuration flag to false to prevent the DebugBar from including its own jQuery.

```yaml
LeKoala\DebugBar\DebugBar:
  include_jquery: false
```

If you are including jQuery yourself, it is expected you include it in `Page::init()`.
Below is an example of how to the jQuery which ships with the framework:

```php
protected function init()
{
    parent::init();
    Requirements::javascript('framework/thirdparty/jquery/jquery.min.js');
}
```

## A quick note about the Security Page

`DebugBarControllerExtension` will include for you all the required assets for DebugBar.

This is done using the `onAfterInit` extension hook, however on the `Security` controller the `onAfterInit` is called before your `init()`
method in the `PageController`.

Since you need to add jQuery before DebugBar this may be a problem, and therefore requirements will NOT be included on the `Security` controller.

If you want DebugBar to work on the `Security` controller, make sure to include all relevant requirements by calling `DebugBar::includeRequirements();` after you include jQuery. When DebugBar is disabled this call will be ignored. Also note that any subsequent call to this method will be ignored as well.
