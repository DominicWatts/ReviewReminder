<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Xigen\ReviewReminder\Cron;

use Psr\Log\LoggerInterface;
use Xigen\ReviewReminder\Helper\Order;
use Magento\Framework\Stdlib\DateTime\DateTime;

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
     * Cleaner constructor.
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Xigen\QuoteCleaner\Helper\Cleaner $helper
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $dateTime
     */
    public function __construct(
        LoggerInterface $logger,
        Order $helper,
        DateTime $dateTime
    ) {
        $this->logger = $logger;
        $this->helper = $helper;
        $this->dateTime = $dateTime;
    }

    /**
     * Execute the cron
     * @return void
     */
    public function execute()
    {
        if ($this->helper->isCronEnabled()) {

            $this->logger->info((string) __(
                '[%1] Reminder Cronjob Start',
                $this->dateTime->gmtDate()
            ));

            $this->helper->sendReminder();

            $report = $this->helper->getReport();

            $this->logger->info((string) __(
                'Result: in %1 sent %2 reminder emails',
                $report['duration'],
                $report['count']
            ));

            $this->logger->info((string) __(
                '[%1] Reminder Cronjob Finish',
                $this->dateTime->gmtDate()
            ));
        }
    }
}
