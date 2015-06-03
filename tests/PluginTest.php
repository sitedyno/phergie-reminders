<?php
/**
 * Phergie plugin for Set timers from IRC to remind you the pizza is done. (https://github.com/sitedyno/phergie-reminders)
 *
 * @link https://github.com/sitedyno/phergie-reminders for the canonical source repository
 * @copyright Copyright (c) 2015 Heath Nail (https://github.com/sitedyno)
 * @license http://phergie.org/license MIT License
 * @package Sitedyno\Phergie\Plugin\Reminders
 */

namespace Sitedyno\Phergie\Tests\Plugin\Reminders;

use Phake;
use Phergie\Irc\Bot\React\EventQueueInterface as Queue;
use Phergie\Irc\Plugin\React\Command\CommandEvent as Event;
use Sitedyno\Phergie\Plugin\Reminders\Plugin;

/**
 * Tests for the Plugin class.
 *
 * @category Sitedyno
 * @package Sitedyno\Phergie\Plugin\Reminders
 */
class PluginTest extends \PHPUnit_Framework_TestCase
{

    public $defaults = [
        'nick' => 'somenick',
        'source' => '#somechan',
        'command' => 'PRIVMSG',
        'queueCommand' => 'ircPRIVMSG'
    ];

    /**
     * Clean up after tests
     */
    public static function tearDownAfterClass()
    {
        $dir = getcwd();
        $file = $dir . DIRECTORY_SEPARATOR . '.phergie-reminders';
        if (file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * Set up for tests
     */
    protected function setUp()
    {
        $this->plugin = new Plugin();
        $this->loop = Phake::mock('\React\EventLoop\LoopInterface');
        $this->plugin->setLoop($this->loop);
    }

    /**
     * Generate events for use in tests
     *
     * @param array $params The params that would be returned by $event->getCustomParams()
     * @param array $args Optional overrides for $nick|$source|$command
     *
     * @return mixed
     */
    private function getEvent($params = [], $args = [])
    {
        extract($this->defaults);
        extract($args);
        $event = Phake::mock('\Phergie\Irc\Plugin\React\Command\CommandEvent');
        Phake::when($event)->getCustomParams()->thenReturn($params);
        Phake::when($event)->getNick()->thenReturn($nick);
        Phake::when($event)->getSource()->thenReturn($source);
        Phake::when($event)->getCommand()->thenReturn($command);
        return $event;
    }

    /**
     * Tests that getSubscribedEvents() returns an array.
     * Plugin::getSubscribedEvents()
     */
    public function testGetSubscribedEvents()
    {
        $this->assertInternalType('array', $this->plugin->getSubscribedEvents());
    }

    /**
     * Test that reminder() calls the correct functions.
     * Plugin::reminder()
     */
    public function testFunctionReminder()
    {
        $plugin = Phake::mock('\Sitedyno\Phergie\Plugin\Reminders\Plugin');
        $event = Phake::mock('\Phergie\Irc\Plugin\React\Command\CommandEvent');
        $queue = Phake::mock('\Phergie\Irc\Bot\React\EventQueueInterface');
        $commands = ['add', 'edit', 'delete', 'cancel', 'show', 'rename'];
        Phake::when($plugin)->reminder($event, $queue)->thenCallParent();

        foreach($commands as $command) {
            Phake::when($event)->getCustomParams()->thenReturn([$command]);
            $plugin->reminder($event, $queue);
            Phake::verify($plugin)->{$command . 'Reminder'}($event, $queue);
        }

        Phake::when($event)->getCustomParams()->thenReturn(['list']);
        $plugin->reminder($event, $queue);
        Phake::verify($plugin)->listReminders($event, $queue);

        Phake::when($event)->getCustomParams()->thenReturn(['stop']);
        $plugin->reminder($event, $queue);
        Phake::verify($plugin, Phake::times(2))->cancelReminder($event, $queue);

        Phake::when($event)->getCustomParams()->thenReturn(['message']);
        $plugin->reminder($event, $queue);
        Phake::verify($plugin)->setReminderMessage($event, $queue);
    }

    /**
     * Test addReminder
     * Plugin::addReminder()
     */
    public function testAddReminder()
    {
        extract($this->defaults);
        // successful addition
        $name = 'reminder1';
        $event = $this->getEvent(['add', $name, '1', 'second']);
        $queue = Phake::mock('\Phergie\Irc\Bot\React\EventQueueInterface');
        $this->plugin->addReminder($event, $queue);
        $this->assertArrayHasKey($nick, $this->plugin->reminders);
        $this->assertArrayHasKey($name, $this->plugin->reminders[$nick]);
        $this->assertEquals($nick, $this->plugin->reminders[$nick][$name]['nick']);
        $this->assertEquals($name, $this->plugin->reminders[$nick][$name]['name']);
        $this->assertEquals('1 second', $this->plugin->reminders[$nick][$name]['time']);
        $this->assertEquals($queueCommand, $this->plugin->reminders[$nick][$name]['command']);
        $this->assertEquals(1, $this->plugin->reminders[$nick][$name]['seconds']);
    }

    /**
     * Test adding an existing reminder
     * Plugin::addReminder()
     */
    public function testAddExistingReminder()
    {
        extract($this->defaults);
        // successful addition
        $name = 'reminder1';
        $event = $this->getEvent(['add', $name, '2', 'seconds']);
        $queue = Phake::mock('\Phergie\Irc\Bot\React\EventQueueInterface');
        $this->plugin->addReminder($event, $queue);
        $this->assertEquals(1, $this->plugin->reminders[$nick][$name]['seconds']);
    }

    /**
     * Test adding with a bad time phrase
     * Plugin::addReminder()
     */
    public function testAddReminderBadTimePhrase()
    {
        extract($this->defaults);
        $name = 'badtimephrase';
        $event = $this->getEvent(['add', $name, 'bad', 'time', 'phrase']);
        $queue = Phake::mock('\Phergie\Irc\Bot\React\EventQueueInterface');
        $this->plugin->addReminder($event, $queue);
        $this->assertFalse(isset($this->plugin->reminders[$nick][$name]));
    }

    /**
     * Test starting reminders
     * Plugin::startReminder()
     */
    public function testStartReminder()
    {
        extract($this->defaults);
        $name = 'reminder1';
        $event = $this->getEvent(['start', $name]);
        $queue = Phake::mock('\Phergie\Irc\Bot\React\EventQueueInterface');
        $this->assertTrue(empty($this->plugin->activeTimers));
        Phake::reset($this->loop);
        $this->plugin->startReminder($event, $queue);
        $this->assertTrue(array_key_exists($name, $this->plugin->activeTimers[$nick]));
        Phake::verify($this->loop)->addTimer(1, Phake::ignoreRemaining());
    }

    /**
     * Test starting a non-existent reminder
     * Plugin::startReminder()
     */
    public function testStartNonExistentReminder()
    {
        extract($this->defaults);
        $name = 'ficticiousReminder';
        $event = $this->getEvent(['start', $name]);
        $queue = Phake::mock('\Phergie\Irc\Bot\React\EventQueueInterface');
        $this->plugin->startReminder($event, $queue);
        $this->assertTrue(empty($this->activeTimers));
    }

    /**
     * Test renaming a reminder
     * Plugin::renameReminder()
     */
    public function testRenameReminder()
    {
        extract($this->defaults);
        $name = 'reminder1';
        $newName = 'newReminder1';
        $event = $this->getEvent(['rename', $name, $newName]);
        $queue = Phake::mock('\Phergie\Irc\Bot\React\EventQueueInterface');
        $this->plugin->renameReminder($event, $queue);
        $this->assertFalse(isset($this->plugin->reminders[$nick][$name]));
        $this->assertTrue(isset($this->plugin->reminders[$nick][$newName]));
    }

    /**
     * Test setting a custom message for a reminder
     * Plugin::setReminderMessage()
     */
    public function testSetReminderMessage()
    {
        extract($this->defaults);
        $name = 'newReminder1';
        $event = $this->getEvent(['message', $name, 'custom', 'message']);
        $queue = Phake::mock('\Phergie\Irc\Bot\React\EventQueueInterface');
        $this->plugin->setReminderMessage($event, $queue);
        $this->assertTrue(isset($this->plugin->reminders[$nick][$name]['message']));
        $this->assertEquals('custom message', $this->plugin->reminders[$nick][$name]['message']);
    }
}
