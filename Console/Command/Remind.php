<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Xigen\ReviewReminder\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\Filesystem\DirectoryList;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Psr\Log\LoggerInterface;
use Magento\Framework\Console\Cli;
use Magento\Framework\App\Area;
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

        $this->output->writeln((string) __(
            "[%1] Start",
            $this->dateTime->gmtDate()
        ));

        $lock = $factory->createLock('review-reminder');
        if ($lock->acquire()) {
            
            $this->helper->sendReminder();

            $lock->release();
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
