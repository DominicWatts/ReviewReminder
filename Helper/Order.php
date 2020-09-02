<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Xigen\ReviewReminder\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class Order extends AbstractHelper
{
    const CONFIG_XML_OLDER_THAN = 'review_reminder/options/remind_order_older_than';
    const CONFIG_XML_LIMIT = 'review_reminder/options/limit';
    const CONFIG_XML_CRON = 'review_reminder/options/cron';

    const MAX_LIMIT = 500;
    const MIN_OLDER_THAN = 2;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var int
     */
    private $limit;

    /**
     * @var \Magento\Framework\DB\Adapter\AdapterInterface
     */
    private $connection;

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    private $resource;

    /**
     * @var int
     */
    private $orderOlderThan;

    /**
     * @var array
     */
    private $report = [];

    /**
     * @var array
     */
    protected $result = [];

    /**
     * @var null
     */
    private $startTime = null;

    /**
     * @var null
     */
    private $endTime = null;

    /**
     * @var string
     */
    protected $tableName;

    /**
     * Order constructor.
     * @param Magento\Framework\App\Helper\Context $context
     * @param Psr\Log\LoggerInterface $logger
     * @param Magento\Framework\App\ResourceConnection $resource
     */
    public function __construct(
        Context $context,
        LoggerInterface $logger,
        ResourceConnection $resource
    ) {
        $this->logger = $logger;
        $this->connection = $resource->getConnection();
        $this->resource = $resource;
        parent::__construct($context);
    }

    /**
     * Initiate variables
     * @return void
     */
    public function initiate()
    {
        $this->setLimit(min($this->getLimitFromConfig(), self::MAX_LIMIT));
        $this->setOrderOlderThan(max($this->getOrderOlderThanFromConfig(), self::MIN_OLDER_THAN));
        $this->setStartTime(time());
        $this->setReport([]);
        $this->setResult([]);
    }

    /**
     * Get order table name
     * @return string
     */
    public function getTableName()
    {
        return $this->resource->getTableName('order');
    }

    /**
     * Get limit
     * @return string
     */
    public function getLimitFromConfig()
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_XML_LIMIT,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Is cron enabled
     * @return string
     */
    public function isCronEnabled()
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_XML_CRON,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get orders older than
     * @return string
     */
    public function getOrderOlderThanFromConfig()
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_XML_OLDER_THAN,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function sendReminder()
    {
        $this->getOrders();
        $result = $this->getResult();
        foreach($result as $item) {
            var_dump($item);
            die();
        }
    }

    /**
     * Build order query
     * @return void
     */
    public function getOrders()
    {
        $this->initiate();
        $select = $this->connection
            ->select()
            ->from($this->getTableName())
            ->where('NOT ISNULL(customer_id) AND customer_id != 0')
            ->where('created_at < DATE_SUB(NOW(), INTERVAL ? DAY)', $this->getOrderOlderThan())
            ->limit($this->getLimit());

        $this->doSelect($select);
    }

    /**
     * Perform order query
     * @return void
     */
    public function doSelect($select)
    {
        try {
            $result = $this->connection->fetchAll($select);
            $this->setReport([
                'duration' => time() - $this->getStartTime(),
                'count' => count($result)
            ]);
            $this->setResult($result);
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }
    }

    /**
     * @return array
     */
    public function getReport(): array
    {
        return $this->report;
    }

    /**
     * @return array
     */
    public function getResult(): array
    {
        return $this->result;
    }

    /**
     * @param array $result
     */
    public function setResult(array $result)
    {
        $this->result = $result;
    }

    /**
     * @param array $report
     */
    public function setReport(array $report)
    {
        $this->report = $report;
    }

    /**
     * @return null
     */
    public function getStartTime()
    {
        return $this->startTime;
    }

    /**
     * @param null $startTime
     */
    public function setStartTime($startTime)
    {
        $this->startTime = $startTime;
    }

    /**
     * @param int $orderOlderThan
     */
    public function setOrderOlderThan(int $orderOlderThan)
    {
        $this->orderOlderThan = $orderOlderThan;
    }

    /**
     * @param int $limit
     */
    public function setLimit(int $limit)
    {
        $this->limit = $limit;
    }

    /**
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @return int
     */
    public function getOrderOlderThan(): int
    {
        return $this->orderOlderThan;
    }
}
