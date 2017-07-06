# Configuration

Wherever possible, features and settings have been made configurable. You can see a list of the default configuration settings by looking at `_config/debugbar.yml`. To modify any of these settings you can define a YAML configuration block in your `mysite/_config` folder, for example:

**\_mysite/config/debugbar.yml:**
```yaml
---
Name: mysitedebugbar
---
DebugBar:
  enabled_in_admin: false
  query_limit: 500
```

## Settings

|Setting|Type|Description|
|---|---|---|
| `enable_storage` | bool | Store all previous request in the temp folder (enabled by default) |
| `auto_debug` | bool| Automatically collect debug and debug_request data (disabled by default) |
| `ajax` | bool | Automatically inject data in XHR requests (disabled by default, since this makes the Chrome request inspector very slow due to the large amount of header data) |
| `force_proxy` | bool | Always use the database proxy instead of built in PDO collector (enabled by default) |
| `check_local_ip` | bool | Do not display the DebugBar if not using a local ip (enabled by default) |
| `find_source` | bool | Trace which file generates a database query (enabled by default) |
| `enabled_in_admin` | bool | enable DebugBar in the CMS (enabled by default) |
| `include_jquery` | bool | Let DebugBar include jQuery. Set this to false to include your own jQuery version |
| `query_limit` | int | Maximum number of database queries to display (200 by default for performance reasons) |
| `warn_query_limit` | int | Number of database queries before a warning will be displayed |
| `warn_dbqueries_threshold_seconds` | int | Threshold (seconds) for how long a database query can run for before it will be shown as a warning |
| `warn_request_time_seconds` | int | Threshold (seconds) for what constitutes a *dangerously* long page request (upper limit) |
| `warn_warning_ratio` | float | Ratio to divide the warning request time by to get the *warning* level (default 0.5) |

## Disabling the debug bar

You can disable the debug bar with PHP or configuration:

```php
define('DEBUGBAR_DISABLE', true);
```

```yaml
DebugBar:
  disabled: true
```
