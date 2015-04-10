<?php
/**
 * Phergie plugin for Set timers from IRC to remind you the pizza is done. (https://github.com/sitedyno/phergie-reminders)
 *
 * @link https://github.com/sitedyno/phergie-reminders for the canonical source repository
 * @copyright Copyright (c) 2015 Heath Nail (https://github.com/sitedyno)
 * @license http://phergie.org/license MIT License
 * @package Sitedyno\\Phergie\\Plugin\\Reminders
 */

namespace Sitedyno\\Phergie\\Plugin\\Reminders;

use Phergie\Irc\Bot\React\AbstractPlugin;
use Phergie\Irc\Bot\React\EventQueueInterface as Queue;
use Phergie\\Irc\\Plugin\\React\\Command\\CommandEvent as Event;

/**
 * Plugin class.
 *
 * @category Sitedyno
 * @package Sitedyno\\Phergie\\Plugin\\Reminders
 */
class Plugin extends AbstractPlugin
{
    /**
     * Accepts plugin configuration.
     *
     * Supported keys:
     *
     *
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {

    }

    /**
     *
     *
     * @return array
     */
    public function getSubscribedEvents()
    {
        return [
            'command.' => 'handleCommand',
        ];
    }

    /**
     *
     *
     * @param \Phergie\\Irc\\Plugin\\React\\Command\\CommandEvent $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     */
    public function handleCommand(Event $event, Queue $queue)
    {
    }
}
