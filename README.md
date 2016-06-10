SilverStripe DebugBar module
==================
Use DebugBar in SilverStripe. This is only active in Dev mode.

This module will:

- Enable a custom log writer that will log all messages under the messages tab
- Log framework execution based on available hooks
- Log database calls if you use PDO
- Display current environment

Include the DebugBar in your template simply by calling $RenderDebugBar

Please note that jQuery is excluded from vendors and you are expected to inclure your own jQuery.

Compatibility
==================
Tested with 3.3+

Maintainer
==================
LeKoala - thomas@lekoala.be