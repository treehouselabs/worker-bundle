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
     * @var integer
     */
    private $reshedulePriority;

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
     * @param integer $newPriority A new priority to apply the the job. If omitted the current priority will be used.
     *
     * @return RescheduleException
     */
    public static function create($time, $msg = null, $newPriority = null)
    {
        $re = new RescheduleException($msg);
        $re->setRescheduleDate(new \DateTime('@' . strtotime('+' . $time)));
        $re->setRescheduleMessage($msg);
        $re->setReshedulePriority($newPriority);

        return $re;
    }

    /**
     * @return integer
     */
    public function getReshedulePriority()
    {
        return $this->reshedulePriority;
    }

    /**
     * @param integer $reshedulePriority
     */
    public function setReshedulePriority($reshedulePriority)
    {
        $this->reshedulePriority = $reshedulePriority;
    }
}
