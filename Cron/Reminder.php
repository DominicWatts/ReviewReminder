<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Xigen\ReviewReminder\Cron;

use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Stopwatch\Stopwatch;
use Xigen\ReviewReminder\Helper\Order;

/**
 * Cleaner cron class
 */
class Reminder
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Xigen\ReviewReminder\Helper\Order
     */
    protected $helper;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $dateTime;

    /**
     * @var \Magento\Framework\Filesystem\DirectoryList
     */
    protected $dir;

    /**
     * Cleaner constructor.
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Xigen\QuoteCleaner\Helper\Cleaner $helper
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $dateTime
     */
    public function __construct(
        LoggerInterface $logger,
        Order $helper,
        DateTime $dateTime,
        DirectoryList $dir
    ) {
        $this->logger = $logger;
        $this->helper = $helper;
        $this->dateTime = $dateTime;
        $this->dir = $dir;
    }

    /**
     * Execute the cron
     * @return void
     */
    public function execute()
    {
        if ($this->helper->isCronEnabledFromConfig()) {
            $this->logger->info((string) __(
                '[%1] Reminder Cronjob Start',
                $this->dateTime->gmtDate()
            ));

            $store = new FlockStore($this->dir->getPath('var'));
            $factory = new LockFactory($store);

            $stopwatch = new Stopwatch();
            $stopwatch->start('ReviewReminder');

            $lock = $factory->createLock('review-reminder');

            $result = 0;

            if ($lock->acquire()) {
                $result = $this->helper->sendReminder();
                $lock->release();
            }

            $event = $stopwatch->stop('ReviewReminder');

            $this->logger->info((string) __(
                '[%1] Reminder Cronjob Finish: %2 - Sent : %3',
                $this->dateTime->gmtDate(),
                (string) $event,
                $result
            ));
        }
    }
}
