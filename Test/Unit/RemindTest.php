<?php

namespace Xigen\ReviewReminder\Console\Command;

// phpcs:disable Generic.Files.LineLength

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\State;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Filesystem\DirectoryList;
use Xigen\ReviewReminder\Helper\Order;
use Magento\Framework\Console\Cli;

class RemindTest extends TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $loggerMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $stateMock;

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
     * @var Remind
     */
    private $remind;

    public function setUp(): void
    {
        $this->loggerMock = $this->getMockForAbstractClass(LoggerInterface::class);

        $this->stateMock = $this->getMockBuilder(State::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->dateTimeMock = $this->createMock(DateTime::class);

        $this->directoryListMock = $this->createMock(DirectoryList::class);

        $this->helperMock = $this->createMock(Order::class);

        $this->remind = new Remind(
            $this->loggerMock,
            $this->stateMock,
            $this->dateTimeMock,
            $this->directoryListMock,
            $this->helperMock
        );
    }

    public function testExecute()
    {
        $this->expectErrorMessage("Call to protected method Xigen\ReviewReminder\Console\Command\Remind::execute() from context 'Xigen\ReviewReminder\Console\Command\RemindTest'");
        $this->remind->execute();
    }

    public function testConfigure()
    {
        $this->assertEquals('xigen:reviewreminder:remind', $this->remind->getName());
    }
}
