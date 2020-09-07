<?php

namespace Xigen\ReviewReminder\Cron;

use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Xigen\ReviewReminder\Helper\Order;

class ReminderTest extends TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $loggerMock;

    /**
     * @var DateTime|\PHPUnit\Framework\MockObject\MockObject
     */
    private $dateTimeMock;

    /**
     * @var DirectoryList|\PHPUnit\Framework\MockObject\MockObject
     */
    private $directoryListMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|Order
     */
    private $helperMock;

    /**
     * @var Reminder
     */
    private $reminder;

    public function setUp(): void
    {
        $this->loggerMock = $this->getMockForAbstractClass(LoggerInterface::class);

        $this->helperMock = $this->createMock(Order::class);

        $this->directoryListMock = $this->createMock(DirectoryList::class);

        $this->dateTimeMock = $this->createMock(DateTime::class);

        $this->reminder = new Reminder(
            $this->loggerMock,
            $this->helperMock,
            $this->dateTimeMock,
            $this->directoryListMock
        );
    }

    public function testExecute()
    {
        $return = $this->reminder->execute();
        $this->assertEquals(null, $return);
    }
}
