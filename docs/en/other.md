# Environment and other information

There is a variety of other useful information available via various tabs and indicators on the debug bar. See the screenshot below, and the arrows explained in order from left to right:

![Other indicators](_images/other.png)

## Tabs

* **Session:** Displays a list of everything in your current SilverStripe session
* **Cookies:** Displays a list of all cookies available in a request
* **Parameters:** Displays all GET, POST and REQUEST parameters from the current request
* **Config:** Displays a list of the current [SiteConfig](https://github.com/silverstripe/silverstripe-siteconfig) settings from the CMS
* **Requirements:** Shows a list of all [`Requirements`](https://docs.silverstripe.org/en/developer_guides/templates/requirements/) calls made during a page's execution

## Indicators

Hover over indicators to see:

* **Locale:** The locale currently being used in the site
* **Version:** The current SilverStripe software version
* **User:** The name of the currently logged in user
* **Memory usage:** The amount of memory used to generate a page
* **Request time:** The total time to generate a page (see below)
* Toggles include a popup for history, minimize and close

### Request time

The request time indicator shows you how long it took for the server to render a page, it doesn't include the time your browser takes to render it. You can use browser consoles to profile this aspect.

* For a regular page load, the request time will have a green underline to indicate a healthy speed
* For a slower page load, the request time will have an orange underline to indicate that it took longer than it perhaps should have, but is still OK
* For a long page load, the request time will have a red underline to indicate that it is potentially dangerously slow

The threshold for a dangerously slow page load can be configured with the `DebugBar.warn_request_time_seconds` [configuration](configuration.md) setting.

The threshold for a slower/warning level indicator is defined as a percentage of the dangerous threshold (by default, 50%). This can be adjusted by modifyin the `DebugBar.warn_warning_ratio` configuration setting.
