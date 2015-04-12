# sitedyno/phergie-reminders

[Phergie](http://github.com/phergie/phergie-irc-bot-react/) Phergie plugin to remind you when the pizza is done.

[![Build Status](https://secure.travis-ci.org/sitedyno/phergie-reminders.png?branch=master)](http://travis-ci.org/sitedyno/phergie-reminders)

## Install

The recommended method of installation is [through composer](http://getcomposer.org).

```JSON
{
    "require": {
        "sitedyno/phergie-reminders": "dev-master"
    }
}
```
or
```
composer require sitedyno/phergie-reminders
```

See Phergie documentation for more information on
[installing and enabling plugins](https://github.com/phergie/phergie-irc-bot-react/wiki/Usage#plugins).

## Configuration

Configuration is optional.

```php
return [
    'plugins' => [
        // configuration
        new \Sitedyno\Phergie\Plugin\Reminders\Plugin([
            'cachePath' => '/home/coolperson/phergie', // path to the reminder cache
            'cacheFile' => '.phergie-reminders', '' file name of the reminder cache file
            'forcePMs' => true, // If true the plugin responds in private message only
            'maxListSize' => 5, // If a user has a list of reminders larger than this value, the repsonse  will be in private message
        ])
    ]
];
```

## Tests

To run the unit test suite:

```
curl -s https://getcomposer.org/installer | php
php composer.phar install
./vendor/bin/phpunit
```

## License

Released under the BSD License. See `LICENSE`.
