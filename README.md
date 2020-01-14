# gravityforms-composer-installer

Heavily inspired by [private-composer-installer](https://github.com/ffraenz/private-composer-installer).

## Examples

### Gravity Forms

Add the desired private package to the `repositories` field inside `composer.json`. In this example the entire dist URL of the package will be replaced by an environment variable. Find more about composer repositories in the [composer docs](https://getcomposer.org/doc/05-repositories.md#repositories).

```json
{
  "type": "package",
  "package": {
    "name": "gravityforms/gravityforms",
    "version": "2.4.6.15",
    "type": "wordpress-plugin",
    "dist": {
      "type": "zip",
      "url": "https://www.gravityhelp.com/wp-content/plugins/gravitymanager/api.php?op=get_plugin&slug=gravityforms&key={{WP_PLUGIN_GF_KEY}}"
    },
    "require": {
      "composer/installers": "^1.4",
      "gotoandplay/gravityforms-composer-installer": "^1.0"
    }
  }
},
```

Provide your licence key variable `WP_PLUGIN_GF_KEY` inside the `.env` file.

```
WP_PLUGIN_GF_KEY=abcdef
```

Let composer require the private package.

```bash
composer require gravityforms/gravityforms:*
```
