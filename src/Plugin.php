<?php
/**
 * Phergie plugin to remind you when the pizza is done. (https://github.com/sitedyno/phergie-reminders)
 *
 * @link https://github.com/sitedyno/phergie-reminders for the canonical source repository
 * @copyright Copyright (c) 2015 Heath Nail (https://github.com/sitedyno)
 * @license http://phergie.org/license MIT License
 * @package Sitedyno\Phergie\Plugin\Reminders
 */

namespace Sitedyno\Phergie\Plugin\Reminders;

use DateInterval;
use DateTime;
use Exception;
use Phergie\Irc\Bot\React\AbstractPlugin;
use Phergie\Irc\Bot\React\EventQueueInterface as Queue;
use Phergie\Irc\Client\React\LoopAwareInterface;
use Phergie\Irc\Plugin\React\Command\CommandEvent as Event;
use React\EventLoop\LoopInterface;

/**
 * Plugin class.
 *
 * @category Sitedyno
 * @package Sitedyno\Phergie\Plugin\Reminders
 */
class Plugin extends AbstractPlugin implements LoopAwareInterface
{

    /**
     * Active timers
     *
     * @var array
     */
    public $activeTimers = [];

    /**
     * Filename of the reminders cache
     *
     * @var string
     */
    protected $cacheFile = '.phergie-reminders';

    /**
     * Location of reminders cache
     *
     * @var string
     */
    protected $cachePath = '.';

    /**
     * Force the plugin to use private messages for all responses
     *
     * @var bool
     */
    protected $forcePMs = false;

    /**
     * Loop implentation
     *
     * @var LoopInterface
     */
    protected $loop;

    /**
     * Max size a reminder list can be before being sent to private message
     *
     * @var int
     */
    protected $maxListSize = 5;

    /**
     * Reminders
     *
     * @var array
     */
    public $reminders = [];

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
        if (isset($config['cacheFile'])) {
            $this->cacheFile = $config['cacheFile'];
        }
        if (isset($config['cachePath'])) {
            $this->cachePath = $config['cachePath'];
        }
        if (isset($config['forcePMs'])) {
            $this->forcePMs = $config['forcePMs'];
        }
        if (isset($config['maxListSize'])) {
            $this->maxListSize = $config['maxListSize'];
        }
        if ("." === $this->cachePath || "" === $this->cachePath) {
            if (!$this->cachePath = getcwd()) {
                throw new Exception('Unable to get current working directory.');
            }
        }
        if (!is_dir($this->cachePath) || !is_writable($this->cachePath)) {
            throw new Exception($this->cachePath . " is not a directory or not writable");
        }
        if (substr($this->cachePath, -1) !== DIRECTORY_SEPARATOR) {
            $this->cachePath .= DIRECTORY_SEPARATOR;
        }
        $this->loadReminders();
    }

    /**
     * Sets the event loop to use.
     *
     * @param \React\EventLoop\LoopInterface $loop
     */
    public function setLoop(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    /**
     *
     *
     * @return array
     */
    public function getSubscribedEvents()
    {
        return [
            'command.reminder' => 'reminder',
            'command.reminder.help' => 'reminderHelp'
        ];
    }

    /**
     * Handle the help command
     *
     * @param \Phergie\Irc\Plugin\React\Command\CommandEvent $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     */
    public function reminderHelp(Event $event, Queue $queue)
    {
        $messages = [
            '------------------------------------------------------------------',
            'Usage: reminder add [name] [duration]',
            '[name] - whatever you want to call the reminder',
            '[duration] - how long the reminder should run before reminding you',
            '    You can use phrases like 15 minutes or 240 seconds',
            'Adds a reminder and also starts it',
            '------------------------------------------------------------------',
            'Usage: reminder list',
            'Lists your reminders',
            '------------------------------------------------------------------',
            'Usage: reminder edit [name] [duration]',
            '[name] - The name of an existing reminder',
            '[duration] - how long the reminder should run before reminding you',
            '    You can use phrases like 15 minutes or 240 seconds',
            'Edit an existing reminder',
            '------------------------------------------------------------------',
            'Usage: reminder delete [name]',
            '[name] - The name of an existing reminder',
            'Deletes a reminder',
            '------------------------------------------------------------------',
            'Usage: reminder start [name]',
            'Usage: reminder [name]',
            '[name] - The name of an existing reminder',
            'Start a reminder',
            '------------------------------------------------------------------',
            'Usage: reminder cancel [name]',
            'Usage: reminder stop [name]',
            '[name] - The name of a running reminder',
            'Cancels a running reminder',
            '------------------------------------------------------------------',
            'Usage: reminder show [name]',
            '[name] - The name of an existing reminder',
            'Show the details of a reminder',
            '------------------------------------------------------------------',
            'Usage: reminder rename [current name] [new name]',
            '[current name] - The current name of a reminder',
            '[new name] - The new name of the reminder',
            'Rename a reminder',
            '------------------------------------------------------------------',
            'Usage: reminder message [name] [message]',
            '[name] - The name of an existing reminder',
            '[message] - The message to use when the reminder ends',
            'Sets the message to use when a reminder ends',
            '------------------------------------------------------------------',
        ];
        $nick = $event->getNick();
        $command = 'irc' . $event->getCommand();
        foreach($messages as $key => $message) {
            $this->loop->addTimer($key, function() use ($queue, $command, $nick, $message) {
                $queue->$command($nick, $message);
            });
        }
    }

    /**
     * Handle the reminder command
     *
     * @param \Phergie\Irc\Plugin\React\Command\CommandEvent $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     */
    public function reminder(Event $event, Queue $queue)
    {
        $method = $event->getCustomParams()[0];
        switch ($method) {
            case "add":
                $this->addReminder($event, $queue);
                break;
            case "list":
                $this->listReminders($event, $queue);
                break;
            case "edit":
                $this->editReminder($event, $queue);
                break;
            case "delete":
                $this->deleteReminder($event, $queue);
                break;
            case "start":
                $this->startReminder($event, $queue);
                break;
            case $method === "cancel" || $method === "stop":
                $this->cancelReminder($event, $queue);
                break;
            case "show":
                $this->showReminder($event, $queue);
                break;
            case "rename":
                $this->renameReminder($event, $queue);
                break;
            case "message":
                $this->setReminderMessage($event, $queue);
                break;
            default:
                extract($this->parseReminder($event));
                $name = $method;
                if (isset($this->reminders[$nick][$name])) {
                    $this->startReminder($event, $queue, $this->reminders[$nick][$name]);
                } else {
                    $queue->$command($event->getSource(), 'Unknown command for reminders plugin');
                }
                break;
        }
    }

    protected function parseReminder(Event $event)
    {
        $params = $event->getCustomParams();
        $nick = $event->getNick();
        if (isset($params[1])) {
            $name = $params[1];
        } else {
            $name = null;
        }
        $source = $event->getSource();
        if ($this->forcePMs) {
            $source = $nick;
        }
        $command = 'irc' . $event->getCommand();
        unset($params[0]);
        unset($params[1]);
        $time = join(" ", $params);
        return [
            'nick' => $nick,
            'name' => $name,
            'source' => $source,
            'command' => $command,
            'time' => $time
        ];
    }

    /**
     * Loads reminders from the file system
     */
    public function loadReminders()
    {
        $file = $this->cachePath . $this->cacheFile;
        if (file_exists($file) && false !== $data = file_get_contents($file)) {
            $this->reminders = unserialize($data);
        }
    }

    /**
     * Saves reminders to the file system
     */
    public function saveReminders()
    {
        $serialized = serialize($this->reminders);
        $file = $this->cachePath . $this->cacheFile;
        if (false === file_put_contents($file, $serialized)) {
            throw new Exception('Unable to cache reminders');
        }
    }

    /**
     * List reminders
     *
     * @param \Phergie\Irc\Plugin\React\Command\CommandEvent $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     */
    public function listReminders(Event $event, Queue $queue)
    {
        extract($this->parseReminder($event));
        if (isset($this->reminders[$nick]) && !empty($this->reminders[$nick])) {
            $queue->$command($source, "$nick's reminders:");
            if (count($this->reminders[$nick]) > $this->maxListSize) {
                $source = $nick;
            }
            foreach ($this->reminders[$nick] as $name => $data) {
                $queue->$command($source, "$name: {$data['time']}");
            }
            $queue->$command($source, "End of $nick's reminder list");
        } else {
            $queue->$command($source, "$nick I don't have any reminders for you");
        }
    }

    /**
     * Add a reminder
     *
     * @param \Phergie\Irc\Plugin\React\Command\CommandEvent $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     * @return void;
     */
    public function addReminder(Event $event, Queue $queue)
    {
        extract($this->parseReminder($event));
        if (!$diff = $this->parseTimePhrase($time)) {
            $queue->$command($source, "$nick: Failed to parse '$time' into a time interval.");
            $queue->$command($source, "$nick: Try something like '15 minutes' etc...");
            return;
        }
        try {
            $seconds = $this->toSeconds($diff);
        } catch (Exception $e) {
            $queue->$command($source, $e->getMessage());
            return;
        }
        if (!isset($this->reminders[$nick][$name])) {
            $this->reminders[$nick][$name] = [
                'nick' => $nick,
                'name' => $name,
                'time' => $time,
                'source' => $source,
                'command' => $command,
                'seconds' => $seconds
            ];
            $this->saveReminders();
        } else {
            $queue->$command($source, "Reminder $name already exists. Edit or start it.");
            return;
        }
        $this->startReminder($event, $queue);
    }

    /**
     * Starts a reminder
     *
     * @param \Phergie\Irc\Plugin\React\Command\CommandEvent $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     * @param array $reminder Array of reminder data
     * @return void
     */
    public function startReminder(Event $event, Queue $queue, array $reminder =[])
    {
        if (!empty($reminder)) {
            extract($reminder);
        } else {
            extract($this->parseReminder($event));
            if (isset($this->reminders[$nick][$name])) {
                $reminder = $this->reminders[$nick][$name];
            } else {
                $source = $event->getSource();
                $queue->$command($source, "Reminder $name not found");
                return;
            }
        }
        $source = $event->getSource();
        $reminder['source'] = $source;
        $self = $this;
        $this->activeTimers[$nick][$name] = $this->loop->addTimer($reminder['seconds'], function () use ($self, $queue, $reminder) {
            $self->sendReminder($queue, $reminder);
        });
        $queue->$command($source, "$nick: Reminder has started and due in {$reminder['seconds']} seconds");
    }

    /**
     * Send a reminder when time is up
     *
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     * @param array $reminder
     */
    public function sendReminder($queue, $reminder)
    {
        extract($reminder);
        if (!isset($message)) {
            $message = "$nick: This is me reminding you about $name";
        }
        $queue->$command($source, $message);
        unset($this->activeTimers[$nick][$name]);
    }

    /**
     * Cancel a running timer
     *
     * @param \Phergie\Irc\Plugin\React\Command\CommandEvent $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     */
    public function cancelReminder(Event $event, Queue $queue)
    {
        extract($this->parseReminder($event));
        if (isset($this->activeTimers[$nick][$name])) {
            $this->loop->cancelTimer($this->activeTimers[$nick][$name]);
            unset($this->activeTimers[$nick][$name]);
            $queue->$command($source, "$nick: The $name reminder has been cancelled.");
        } else {
            $queue->$command($source, "$nick: That reminder isn't running.");
        }
    }

    /**
     * Convert a DateInterval to seconds
     *
     * @param DateInterval $diff
     * @return int The number of seconds in the date interval
     */
    protected function toSeconds(DateInterval $diff)
    {
        if ($diff->m > 0 || $diff->y > 0) {
            throw new Exception('I don\'t handle reminders larger than a month.');
        }
        $seconds = $diff->d * 86400
            + $diff->h * 3600
            + $diff->i * 60
            + $diff->s;
        return $seconds;
    }

    /**
     * Attempt to convert a string into a time interval.
     *
     * @param string $time The time string
     * @return DateInterval|false The date interval or false if the time could not be parsed
     */
    protected function parseTimePhrase($time)
    {
        $now = new DateTime();
        try {
            $then = new DateTime($time);
        } catch (Exception $e) {
            return false;
        }
        return $diff = $now->diff($then);
    }

    /**
     * Edit a reminder
     *
     * @param \Phergie\Irc\Plugin\React\Command\CommandEvent $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     */
    public function editReminder(Event $event, Queue $queue)
    {
        extract($this->parseReminder($event));
        if (!$diff = $this->parseTimePhrase($time)) {
            $queue->$command($source, "$nick: Failed to parse '$time' into a time interval.");
            $queue->$command($source, "$nick: Try something like '15 minutes' etc...");
            return;
        }
        try {
            $seconds = $this->toSeconds($diff);
        } catch (Exception $e) {
            $queue->$command($source, $e->getMessage());
            return;
        }
        if (isset($this->reminders[$nick][$name])) {
            $this->reminders[$nick][$name] = [
                'nick' => $nick,
                'name' => $name,
                'time' => $time,
                'source' => $source,
                'command' => $command,
                'seconds' => $seconds
            ];
            $this->saveReminders();
            $queue->$command($source, "$nick: The $name reminder has been edited.");
        } else {
            $queue->$command($source, "$nick: I don't have a $name reminder for you.");
        }
    }

    /**
     * Delete a reminder
     *
     * @param \Phergie\Irc\Plugin\React\Command\CommandEvent $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     */
    public function deleteReminder(Event $event, Queue $queue)
    {
        extract($this->parseReminder($event));
        if (isset($this->reminders[$nick][$name])) {
            unset($this->reminders[$nick][$name]);
            $this->saveReminders();
            $queue->$command($source, "$nick: The $name reminder has been deleted.");
        } else {
            $queue->$command($source, "$nick: I don't have a $name reminder for you.");
        }
    }

    /**
     * Show the guts of a reminder
     *
     * @param \Phergie\Irc\Plugin\React\Command\CommandEvent $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     */
    public function showReminder(Event $event, Queue $queue)
    {
        extract($this->parseReminder($event));
        if (isset($this->reminders[$nick][$name])) {
            extract($this->reminders[$nick][$name]);
            $source = $event->getSource();
            $reply = "$nick - name: $name, time: $time, source: $source, command: $command, seconds: $seconds";
            if (isset($message)) {
                $reply .= ", message: $message";
            }
            $queue->$command($source, $reply);
        } else {
            $queue->$command($source, "$nick: I don't have a $name reminder for you.");
        }
    }

    /**
     * Rename a reminder
     *
     * @param \Phergie\Irc\Plugin\React\Command\CommandEvent $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     */
    public function renameReminder(Event $event, Queue $queue)
    {
        extract($this->parseReminder($event));
        $params = $event->getCustomParams();
        if (!isset($params[2])) {
            $queue->$command($source, "$nick: You need to supply the new reminder's name.");
            return;
        }
        if (isset($this->reminders[$nick][$name])) {
            $newName = $params[2];
            $this->reminders[$nick][$newName] = $this->reminders[$nick][$name];
            unset($this->reminders[$nick][$name]);
            $this->saveReminders();
            $queue->$command($source, "$nick: $name has been renamed to $newName.");
        } else {
            $queue->$command($source, "$nick: I don't have a $name reminder for you.");
        }
    }

    /**
     * Sets the message a reminder displays when a reminder ends
     *
     * @param \Phergie\Irc\Plugin\React\Command\CommandEvent $event
     * @param \Phergie\Irc\Bot\React\EventQueueInterface $queue
     */
    public function setReminderMessage(Event $event, Queue $queue)
    {
        extract($this->parseReminder($event));
        $params = $event->getCustomParams();
        unset($params[0]);
        unset($params[1]);
        if (!empty($params)) {
            $message = implode(' ', $params);
            $this->reminders[$nick][$name]['message'] = $message;
            $this->saveReminders();
            $queue->$command($source, "$nick: Your message has been added.");
        } else {
            $queue->$command($source, "$nick: Please supply a message for your reminder.");
        }
    }
}
