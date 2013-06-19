
# ADP Client

This is a PHP project that talks to ADP's private endpoints. 

## Installation

- Clone this repository
- Run `composer install`

### Setup

To use the CLI:

- Modify `main-auth` with any config options you don't want to supply via CLI (username and password probably)
- Execute as `php main.php`

Examples:

```
php main.php --configfile main-config --adp-timesheet
php main.php --configfile main-config --adp-clock-in
php main.php --configfile main-config --adp-journal
```
