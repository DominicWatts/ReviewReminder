<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Xigen\ReviewReminder\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Stopwatch\Stopwatch;
use Xigen\ReviewReminder\Helper\Order;

class Remind extends Command
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    protected $input;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * @var \Magento\Framework\App\State
     */
    protected $state;

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
     * Remind constructor.
     * @param LoggerInterface $logger
     * @param State $state
     * @param DateTime $dateTime
     * @param DirectoryList $dir
     * @param Order $helper
     */
    public function __construct(
        LoggerInterface $logger,
        State $state,
        DateTime $dateTime,
        DirectoryList $dir,
        Order $helper
    ) {
        $this->logger = $logger;
        $this->state = $state;
        $this->dateTime = $dateTime;
        $this->dir = $dir;
        $this->helper = $helper;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {
        $this->input = $input;
        $this->output = $output;
        $this->state->setAreaCode(Area::AREA_GLOBAL);
        $store = new FlockStore($this->dir->getPath('var'));
        $factory = new LockFactory($store);

        $stopwatch = new Stopwatch();
        $stopwatch->start('ReviewReminder');

        $this->output->writeln((string) __(
            "[%1] Start",
            $this->dateTime->gmtDate()
        ));

        $lock = $factory->createLock('review-reminder');
        if ($lock->acquire()) {
            $result = $this->helper->sendReminder();
            $lock->release();

            $this->output->writeln((string) __(
                "[%1] %2 emails sent",
                $this->dateTime->gmtDate(),
                $result
            ));
        } else {
            $this->output->writeln((string) __(
                "[%1] Process already running",
                $this->dateTime->gmtDate()
            ));
            return Cli::RETURN_FAILURE;
        }

        $this->output->writeln((string) __(
            "[%1] Finish",
            $this->dateTime->gmtDate()
        ));

        $event = $stopwatch->stop('ReviewReminder');
        $this->output->writeln((string) $event);

        return Cli::RETURN_SUCCESS;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName("xigen:reviewreminder:remind");
        $this->setDescription("Send review reminder email");
        parent::configure();
    }
}
