<?php

namespace TreeHouse\WorkerBundle\Exception;

class RescheduleException extends \Exception
{
    /**
     * @var \DateTime
     */
    private $rescheduleDate;

    /**
     * @var string
     */
    private $rescheduleMessage;

    /**
     * @param \DateTime $date
     */
    public function setRescheduleDate(\DateTime $date)
    {
        $this->rescheduleDate = $date;
    }

    /**
     * @return \DateTime
     */
    public function getRescheduleDate()
    {
        return $this->rescheduleDate;
    }

    /**
     * @param string $msg
     */
    public function setRescheduleMessage($msg = null)
    {
        $this->rescheduleMessage = $msg;
    }

    /**
     * @return string
     */
    public function getRescheduleMessage()
    {
        return $this->rescheduleMessage;
    }

    /**
     * Factory method.
     *
     * @param string $time Time difference after which the job should be rescheduled.
     *                     Can be anything that strtotime accepts, without the `+` sign, eg: '10 seconds'
     * @param string $msg
     *
     * @return RescheduleException
     */
    public static function create($time, $msg = null)
    {
        $re = new RescheduleException($msg);
        $re->setRescheduleDate(new \DateTime('@' . strtotime('+' . $time)));
        $re->setRescheduleMessage($msg);

        return $re;
    }
}
