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
use Magento\Framework\DataObject;
use Magento\Framework\App\Area;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Newsletter\Model\Subscriber;
use Magento\Store\Model\Store;
use Laminas\Validator\EmailAddress;

class Order extends AbstractHelper
{
    const CONFIG_XML_OLDER_THAN = 'review_reminder/options/remind_order_older_than';
    const CONFIG_XML_LIMIT = 'review_reminder/options/limit';
    const CONFIG_XML_CRON = 'review_reminder/options/cron';

    const CONFIG_XML_EMAIL_TEMPLATE = 'review_reminder/options/template';
    const CONFIG_XML_EMAIL_IDENTITY = 'review_reminder/options/identity';

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
     * @var \Magento\Customer\Api\Data\CustomerInterfaceFactory
     */
    protected $customerInterfaceFactory;

    /**
     * @var \Magento\Framework\DB\Select
     */
    protected $select;

    /**
     * Order constructor.
     * @param Magento\Framework\App\Helper\Context $context
     * @param Psr\Log\LoggerInterface $logger
     * @param Magento\Framework\App\ResourceConnection $resource
     */
    public function __construct(
        Context $context,
        LoggerInterface $logger,
        ResourceConnection $resource,
        CustomerInterfaceFactory $customerInterfaceFactory
    ) {
        $this->logger = $logger;
        $this->connection = $resource->getConnection();
        $this->resource = $resource;
        $this->customerInterfaceFactory = $customerInterfaceFactory;
        parent::__construct($context);
    }

    /**
     * Initiate variables
     * @return $this
     */
    public function initiate()
    {
        $this->setLimit(min($this->getLimitFromConfig(), self::MAX_LIMIT));
        $this->setOrderOlderThan(max($this->getOrderOlderThanFromConfig(), self::MIN_OLDER_THAN));
        $this->setStartTime(time());
        $this->setReport([]);
        $this->setResult([]);
        return $this;
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
     * @param int $storeId
     * @return string
     */
    public function getLimitFromConfig($storeId = null)
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_XML_LIMIT,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Is cron enabled
     * @param int $storeId
     * @return string
     */
    public function isCronEnabled($storeId = null)
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_XML_CRON,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get orders older than
     * @param int $storeId
     * @return string
     */
    public function getOrderOlderThanFromConfig($storeId = null)
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_XML_OLDER_THAN,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get email template
     * @param int $storeId
     * @return string
     */
    public function getEmailTemplate($storeId = null)
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_XML_EMAIL_TEMPLATE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get email template
     * @param int $storeId
     * @return string
     */
    public function getEmailIdentity($storeId = null)
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_XML_EMAIL_IDENTITY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function sendReminder()
    {
        $result = $this->initiate()
            ->getQuery()
            ->getSelection()
            ->getResult();
        $result = $this->getResult();
        foreach($result as $item) {
            var_dump($item);
            die();
        }
    }

 

    /**
     * Build order query
     * @return $this
     */
    public function getQuery()
    {
        $select = $this->connection
            ->select()
            ->from($this->getTableName())
            ->where('NOT ISNULL(customer_id) AND customer_id != 0')
            ->where('created_at < DATE_SUB(NOW(), INTERVAL ? DAY)', $this->getOrderOlderThan())
            ->limit($this->getLimit());
        $this->setSelect($select);
        return $this;
    }

    /**
     * Perform order query
     * @return $this
     */
    public function getSelection()
    {
        try {
            $result = $this->connection->fetchAll($this->getSelect());
            $this->setReport([
                'duration' => time() - $this->getStartTime(),
                'count' => count($result)
            ]);
            $this->setResult($result);
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }
        return $this;
    }

    /**
     * Get suscription status
     * @param string $email
     * @return bool
     */
    public function getIsSubscribed($email)
    {
        $validator = new EmailAddress();
        if (!$validator->isValid($email)) {
            return false;
        }
        $subscriber = $this->loadByEmail($email);
        if (!$subscriber->getId() || $subscriber->getStatus() != Subscriber::STATUS_SUBSCRIBED) {
            return $subscriber;
        }
        return true;
    }

    /**
     * Load subscriber data from resource model by email
     * @param string $subscriberEmail
     * @param int $storeId
     * @return $this
     */
    public function loadByEmail($subscriberEmail, $storeId = Store::DISTRO_STORE_ID)
    {
        $storeId = $this->storeManager->getStore()->getId();
        $customerData = [
            'store_id' => $storeId,
            'email'=> $subscriberEmail
        ];

        /** @var \Magento\Customer\Api\Data\CustomerInterface $customer */
        $customer = $this->customerInterfaceFactory->create();
        $this->dataObjectHelper->populateWithArray(
            $customer,
            $customerData,
            CustomerInterface::class
        );

        $subscriber = $this->subscriberFactory->create();

        $array = $subscriber->getResource()
            ->loadByCustomerData($customer);

        return $subscriber->addData($array);
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

    /**
     * Send transactional email
     * @param array $vars
     * @return void
     */
    public function sendTransactionalEmail($vars = [])
    {
        $email = $vars['email'] ?? null;
        $storeId = $vars['store'] ?? Store::DISTRO_STORE_ID;

        if (empty($vars) || !$email) {
            return;
        }

        $this->inlineTranslation->suspend();
        try {
            $postObject = new DataObject();
            $postObject->setData($vars);

            $this->transportBuilder->setTemplateIdentifier(
                $this->getEmailTemplate($storeId)
            )->setTemplateOptions(
                [
                    'area' => Area::AREA_FRONTEND,
                    'store' => $storeId,
                ]
            )->setTemplateVars(
                [
                    'firstname' => $vars['firstname'] ?? null,
                    'lastname' => $vars['lastname'] ?? null,
                    'email' => $email
                ]
            )->setFrom(
                $this->getEmailIdentity($storeId)
            )->addTo(
                $this->escaper->escapeHtml($vars['email']),
                $this->escaper->escapeHtml($vars['firstname'])
            );

            $transport = $this->transportBuilder->getTransport();
            $transport->sendMessage();

            $this->inlineTranslation->resume();

            return true;
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }
        return false;
    }

    /**
     * @return Magento\Framework\DB\Select
     */
    public function getSelect(): \Magento\Framework\DB\Select
    {
        return $this->select;
    }

    /**
     * @param Magento\Framework\DB\Select $select
     */
    public function setSelect(\Magento\Framework\DB\Select $select)
    {
        $this->select = $select;
    }

}
