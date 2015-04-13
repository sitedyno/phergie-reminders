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
        // dependencies
        new \Phergie\Irc\Plugin\React\Command\Plugin,

        // optional dependencies
        new \Phergie\Irc\Plugin\React\CommandHelp\Plugin,

        // configuration
        new \Sitedyno\Phergie\Plugin\Reminders\Plugin([
            // path to the reminder cache
            'cachePath' => '/home/coolperson/phergie',
            // file name of the reminder cache file
            'cacheFile' => '.phergie-reminders',
            // If true the plugin responds in private message only
            'forcePMs' => true,
            // If a user has a list of reminders larger than this value
            // the repsonse  will be in private message
            'maxListSize' => 5,
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

Released under the MIT License. See `LICENSE`.
