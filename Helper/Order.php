<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Xigen\ReviewReminder\Helper;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\App\Area;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\Escaper;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Newsletter\Model\Subscriber;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Psr\Log\LoggerInterface;
use Zend\Validator\EmailAddress;
use Magento\Framework\Exception\LocalizedException;
use Magento\Directory\Model\CurrencyFactory;

class Order extends AbstractHelper
{
    const CONFIG_XML_ENABLED = 'review_reminder/options/enabled';
    const CONFIG_XML_OLDER_THAN = 'review_reminder/options/remind_order_older_than';
    const CONFIG_XML_LIMIT = 'review_reminder/options/limit';
    const CONFIG_XML_CRON = 'review_reminder/options/cron';

    const CONFIG_XML_EMAIL_TEMPLATE = 'review_reminder/options/template';
    const CONFIG_XML_EMAIL_IDENTITY = 'review_reminder/options/identity';

    const MAX_LIMIT = 500;
    const MIN_OLDER_THAN = 2;

    const EMAIL_SUCCESS = 1;
    const EMAIL_ERROR_PARAM = 2;
    const EMAIL_ERROR_EXCEPTION = 3;

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
     * @var DataObjectHelper
     */
    private $dataObjectHelper;

    /**
     * @var SubscriberFactory
     */
    protected $subscriberFactory;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    protected $customerRepositoryInterface;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepositoryInterface;

    /**
     * @var \Magento\Framework\Translate\Inline\StateInterface
     */
    protected $inlineTranslation;

    /**
     * @var \Magento\Framework\Mail\Template\TransportBuilder
     */
    protected $transportBuilder;

    /**
     * @var \Magento\Framework\Escaper
     */
    protected $escaper;

    /**
     * @var bool
     */
    protected $isEnabled;

    /**
     * @var bool
     */
    protected $isCronEnabled;

    /**
     * @var \Magento\Directory\Model\CurrencyFactory
     */
    protected $currencyFactory;

    /**
     * Order constructor.
     * @param Context $context
     * @param LoggerInterface $logger
     * @param ResourceConnection $resource
     * @param CustomerInterfaceFactory $customerInterfaceFactory
     * @param DataObjectHelper $dataObjectHelper
     * @param SubscriberFactory $subscriberFactory
     * @param CustomerRepositoryInterface $customerRepositoryInterface
     * @param OrderRepositoryInterface $orderRepositoryInterface
     * @param StateInterface $inlineTranslation
     * @param TransportBuilder $transportBuilder
     * @param Escaper $escaper
     */
    public function __construct(
        Context $context,
        LoggerInterface $logger,
        ResourceConnection $resource,
        CustomerInterfaceFactory $customerInterfaceFactory,
        DataObjectHelper $dataObjectHelper,
        SubscriberFactory $subscriberFactory,
        CustomerRepositoryInterface $customerRepositoryInterface,
        OrderRepositoryInterface $orderRepositoryInterface,
        StateInterface $inlineTranslation,
        TransportBuilder $transportBuilder,
        Escaper $escaper,
        CurrencyFactory $currencyFactory
    ) {
        $this->logger = $logger;
        $this->connection = $resource->getConnection();
        $this->resource = $resource;
        $this->customerInterfaceFactory = $customerInterfaceFactory;
        $this->dataObjectHelper = $dataObjectHelper;
        $this->subscriberFactory = $subscriberFactory;
        $this->customerRepositoryInterface = $customerRepositoryInterface;
        $this->orderRepositoryInterface = $orderRepositoryInterface;
        $this->inlineTranslation = $inlineTranslation;
        $this->transportBuilder = $transportBuilder;
        $this->escaper = $escaper;
        $this->currencyFactory = $currencyFactory;
        parent::__construct($context);
    }

    /**
     * Initiate variables
     * @return $this
     * @throws LocalizedException
     */
    public function initiate()
    {
        $this->setIsEnabled($this->isEnabledFromConfig());
        if (!$this->getIsEnabled()) {
            throw new LocalizedException(__('Review reminder disabled in config'));
        }
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
        return $this->resource->getTableName('sales_order');
    }

    /**
     * Get limit
     * @param int $storeId
     * @return int
     */
    public function getLimitFromConfig($storeId = null)
    {
        return (int) $this->scopeConfig->getValue(
            self::CONFIG_XML_LIMIT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Is module enabled
     * @param int $storeId
     * @return string
     */
    public function isEnabledFromConfig($storeId = null)
    {
        return (bool) $this->scopeConfig->getValue(
            self::CONFIG_XML_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Is cron enabled
     * @param int $storeId
     * @return string
     */
    public function isCronEnabledFromConfig($storeId = null)
    {
        return (bool) $this->scopeConfig->getValue(
            self::CONFIG_XML_CRON,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get orders older than
     * @param int $storeId
     * @return int
     */
    public function getOrderOlderThanFromConfig($storeId = null)
    {
        return (int) $this->scopeConfig->getValue(
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

    /**
     * Send Reminder
     * @return int
     */
    public function sendReminder()
    {
        $successCount = 0;
        try {
            $result = $this->initiate()
                ->getQuery()
                ->getSelection()
                ->getResult();
        } catch (\Exception $e) {
            $this->logger->critical($e);
            return $successCount;
        }

        foreach ($result as $item) {
            if ($subscriber = $this->getSubscriber($item['customer_email'])) {
                if ($order = $this->getOrderById((int) $item['entity_id'])) {
                    $result = $this->sendTransactionalEmail([
                        'email' => $order->getCustomerEmail(),
                        'firstname' => $order->getCustomerFirstname(),
                        'lastname' => $order->getCustomerLastname(),
                        'items' => $order->getAllVisibleItems(),
                        'store' => $order->getStoreId(),
                        'currency' => $order->getOrderCurrency()
                    ]);

                    try {
                        if ($result == self::EMAIL_SUCCESS) {
                            $successCount++;
                        }
                        $order->setSentReviewRequest($result);
                        $order->save();
                    } catch (\Exception $e) {
                        $this->logger->critical($e);
                    }
                }
            }
        }
        return $successCount;
    }

    /**
     * Get customer by Id
     * @param int $customerId
     * @return bool|\Magento\Customer\Api\Data\CustomerInterface
     */
    public function getCustomerById(int $customerId)
    {
        try {
            return $this->customerRepositoryInterface->getById($customerId);
        } catch (\Exception $e) {
            $this->logger->critical($e);
            return false;
        }
    }

    /**
     * Get order by Id
     * @param int $orderId
     * @return bool|\Magento\Sales\Api\Data\OrderInterface
     */
    public function getOrderById(int $orderId)
    {
        try {
            return $this->orderRepositoryInterface->get($orderId);
        } catch (\Exception $e) {
            $this->logger->critical($e);
            return false;
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
            ->where('ISNULL(sent_review_request) OR sent_review_request < 1')
            ->where('NOT ISNULL(customer_email) AND customer_email != ""')
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
     * Get subcriber
     * @param string $email
     * @param int $storeId
     * @return bool
     */
    public function getSubscriber(string $email, int $storeId = Store::DISTRO_STORE_ID)
    {
        $validator = new EmailAddress();
        if (!$validator->isValid($email)) {
            return false;
        }
        $subscriber = $this->loadByEmail($email, $storeId);
        if (!$subscriber->getId() || $subscriber->getStatus() != Subscriber::STATUS_SUBSCRIBED) {
            return false;
        }
        return $subscriber;
    }

    /**
     * Load subscriber data from resource model by email
     * @param string $subscriberEmail
     * @param int $storeId
     * @return $this
     */
    public function loadByEmail(string $subscriberEmail, int $storeId = Store::DISTRO_STORE_ID)
    {
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
     * @return int
     */
    public function sendTransactionalEmail($vars = [])
    {
        $email = $vars['email'] ?? null;
        $storeId = $vars['store'] ?? Store::DISTRO_STORE_ID;

        if (empty($vars) || !$email) {
            return self::EMAIL_ERROR_PARAM;
        }

        $this->inlineTranslation->suspend();
        try {
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
                    'email' => $email,
                    'items' => $vars['items'] ?? null,
                    'currency' => $vars['currency'] ?? null,
                    'helper' => $this
                ]
            )->setFrom(
                $this->getEmailIdentity($storeId)
            )->addTo(
                $this->escaper->escapeHtml($vars['email'] ?? null),
                $this->escaper->escapeHtml($vars['firstname'] ?? null)
            );

            $transport = $this->transportBuilder->getTransport();
            $transport->sendMessage();

            $this->inlineTranslation->resume();

            return self::EMAIL_SUCCESS;
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }
        return self::EMAIL_ERROR_EXCEPTION;
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

    /**
     * @return bool
     */
    public function getIsEnabled(): bool
    {
        return $this->isEnabled;
    }

    /**
     * @param bool $isEnabled
     */
    public function setIsEnabled(bool $isEnabled)
    {
        $this->isEnabled = $isEnabled;
    }

    /**
     * @return bool
     */
    public function getIsCronEnabled(): bool
    {
        return $this->isCronEnabled;
    }

    /**
     * @param bool $isCronEnabled
     */
    public function setIsCronEnabled(bool $isCronEnabled)
    {
        $this->isCronEnabled = $isCronEnabled;
    }
    
    /** 
     * Format price with symbol
     * @param float $price 
     * @param string $symbol 
     * @return string 
     */ 
    public function formatPrice(float $price, string $symbol): string
    { 
        return $this->currencyFactory 
            ->create() 
            ->format( 
                $price, 
                ['symbol' => $symbol], 
                false 
            ); 
    }
}
