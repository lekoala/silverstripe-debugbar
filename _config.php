<?php
if (!defined('DEBUGBAR_DIR')) {
    define('DEBUGBAR_DIR', basename(__DIR__));
}

// Add a custom logger that logs everything under the Messages tab
SS_Log::add_writer(new DebugBarLogWriter(), SS_Log::DEBUG, '<=');
