<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/5/16
 * Time: 14:22
 */
namespace Reloday\Gms\Help;

use Reloday\Gms\Models\Task;

class Reminder
{
    const QUEUE_MAIL_KEY = 'reminder-mail';
    const QUEUE_NOTIFY_KEY = 'reminder-notify';

    /**
     * @param Task $task
     * @param bool $mail_enabled
     * @param bool $notify_enabled
     */
    public function makeReminder(Task $task, $mail_enabled = true, $notify_enabled = true)
    {
        $queue = $this->redis;

        $data = [];
        // Make tube name
        if (!empty($task->getReminderDate())) {
            $time_unit = $this->getTimeUnit($task->getReminderTimeUnit());

            $start = new \DateTime($task->getReminderDate());
            $end = new \DateTime($task->getReminderDate());

            if ($task->getBeforeAfter() == 0) { // Before
                $start->sub(date_interval_create_from_date_string($task->getReminderTime() . ' ' . $time_unit));
            } else { // After
                $end->add(date_interval_create_from_date_string($task->getReminderTime() . ' ' . $time_unit));
            }

            // Compare with current time
            $now = new \DateTime();
            if ($now > $start) {
                $start = $now;
            }

            while ($end > $start) {
                $data[] = $start->format('m/d/Y H:i:s');
                $start->add(
                    date_interval_create_from_date_string($task->getRecurrenceTime() . ' ' . $this->getTimeUnit($task->getRecurrenceTimeUnit()))
                );
            }

            if (empty($time)) {
                $data[] = $start->format('m/d/Y H:i:s');
            }
        }

        /**
         * Add or modify task in mail queue
         */
        if ($mail_enabled)
            $this->addToQueue($queue, $task, $data, self::QUEUE_MAIL_KEY);

        /**
         * Add or modify task in notify queue
         */
        if ($notify_enabled)
            $this->addToQueue($queue, $task, $data, self::QUEUE_NOTIFY_KEY);

    }

    /**
     * @param $time
     * @return string
     */
    protected function getTimeUnit($time)
    {

        switch ($time) {
            case 'I':
                $time = 'minutes';
                break;
            case 'H':
                $time = 'hours';
                break;
            case 'D':
                $time = 'days';
                break;
            case 'W':
                $time = 'weeks';
                break;
            case 'M' :
                $time = 'months';
                break;
            case 'Y' :
                $time = 'years';
                break;
            default:
                $time = 'hours';
                break;
        }

        return $time;
    }

    /**
     * @param \Redis $queue
     * @param Task $task
     * @param array $time {'10/20/2015 10:45:00', '10/20/2015 11:00:00'}
     * @param string $tube
     */
    protected function addToQueue(\Redis $queue, Task $task, $time = [], $tube = '')
    {
        if (empty($tube)) {
            $tube = self::QUEUE_MAIL_KEY;
        }

        // Find task in list reminder mail
        $total = $queue->lLen($tube);
        $inc = 0;
        while ($queue->lRange($tube, 0, -1) & $inc < $total) {
            $inc++;
            $item = $queue->rPop($tube);
            $content = json_decode($item, true);
            if (is_array($content)) {
                if (isset($content[$task->getId()])) {
                    // Trim element found from list
                    break;
                }
            }

            $queue->lPush($tube, $item);
        }

        // Add task to reminder mail
        if (!empty($time)) {
            $queue->lPush($tube, json_encode([
                $task->getId() => [
                    'time' => $time,
                    'content' => $task->toArray()
                ]
            ]));
        }
    }

    /**
     * Get config when set reminder: do send mail, do notify to user on web browser
     * @return array
     */
    public function getReminderConfig() {

        // Get config of reminder
        // ....

        return [
            'mail_enable' => true,
            'notify_enable' => true
        ];
    }

    /**
     * @param bool $mail_enabled
     * @param bool $notify_enabled
     */
    public function changeReminderConfig($mail_enabled = true, $notify_enabled = true) {
        
    }
}