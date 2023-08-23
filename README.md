# Access League Apps secure admin API using PHP

League Apps API Docs: http://leagueapps.com/api/

## Installation - PHP ONLY

```shell
composer intall guzzlehttp/guzzle web-token/jwt-framework
```

## Installation - WordPress

```shell
composer intall web-token/jwt-framework
```

## Update
```php
define( 'LEAGUE_APPS_SITE_ID', 'YOUR_SITE_ID' );               // found in the url of your LA site
define( 'LEAGUE_APPS_PRIVATE_KEY_ID', 'YOUR_PRIVATE_KEY_ID' ); // generated in the LA admin
define( 'LEAGUE_APPS_PRIVATE_KEY_DIR', 'path/to/keys/' );
```

### WordPress Notes

- Uses built in WordPress HTTP API request functions
    - `wp_remote_post`
    - `wp_remote_get`
    - `wp_remote_retrieve_response_code`
    - `wp_remote_retrieve_body`
- Uses WordPress transients to cache the responses - _cache time is 7 days_
    - `get_transient`
    - `set_transient`

_**Note to future self:** turn this into a plugin_
