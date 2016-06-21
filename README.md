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

Installation
==================

Since this extension will increase memory footprint, it is recommended to install it only in dev mode

Either run:

    composer require lekoala/silverstripe-debugbar --dev

Or add: 

    lekoala/silverstripe-debugbar: "dev-master"

to require-dev in your composer.json file

NOTE: somehow, the Only environment flag in the yml config will prevent
the controller extension to be initialized properly. Meanwhile, feel free
to add this in mysite/_config.php to make sure the controller has the proper
extension.

    if (Director::isDev()) {
        if (!Controller::has_extension('DebugBarControllerExtension')) {
            Controller::add_extension('DebugBarControllerExtension');
        }
    }

Compatibility
==================
Tested with 3.3+

Maintainer
==================
LeKoala - thomas@lekoala.be