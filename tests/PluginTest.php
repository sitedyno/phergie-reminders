<?php
/**
 * Phergie plugin for Set timers from IRC to remind you the pizza is done. (https://github.com/sitedyno/phergie-reminders)
 *
 * @link https://github.com/sitedyno/phergie-reminders for the canonical source repository
 * @copyright Copyright (c) 2015 Heath Nail (https://github.com/sitedyno)
 * @license http://phergie.org/license MIT License
 * @package Sitedyno\\Phergie\\Plugin\\Reminders
 */

namespace Sitedyno\\Phergie\\Tests\\Plugin\\Reminders;

use Phake;
use Phergie\Irc\Bot\React\EventQueueInterface as Queue;
use Phergie\\Irc\\Plugin\\React\\Command\\CommandEvent as Event;
use Sitedyno\\Phergie\\Plugin\\Reminders\Plugin;

/**
 * Tests for the Plugin class.
 *
 * @category Sitedyno
 * @package Sitedyno\\Phergie\\Plugin\\Reminders
 */
class PluginTest extends \PHPUnit_Framework_TestCase
{


    /**
     * Tests that getSubscribedEvents() returns an array.
     */
    public function testGetSubscribedEvents()
    {
        $plugin = new Plugin;
        $this->assertInternalType('array', $plugin->getSubscribedEvents());
    }
}
