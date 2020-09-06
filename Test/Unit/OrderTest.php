<?php

namespace Xigen\ReviewReminder\Helper;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Escaper;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Store\Model\ScopeInterface;
use Xigen\ReviewReminder\Helper\Order;

class OrderTest extends TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $scopeConfigMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $contextMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|LoggerInterface
     */
    private $loggerMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $resourceMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $customerInterfaceFactoryMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $dataObjectHelperMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $subscriberFactoryMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $customerRepositoryInterfaceMock;

    /**
     * @var OrderRepositoryInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $orderRepositoryInterfaceMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $inlineTranslationMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $transportBuilderMock;

    /**
     * @var Escaper|\PHPUnit\Framework\MockObject\MockObject
     */
    private $escaperMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $currencyFactoryMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $helperMock;

    /**
     * @var Order
     */
    private $helper;

    /**
     * @var AdapterInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $connectionMock;
    
    /**
     * @var Select|\PHPUnit\Framework\MockObject\MockObject
     */
    private $selectMock;

    public function setUp(): void
    {
        $this->contextMock = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->loggerMock = $this->getMockForAbstractClass(LoggerInterface::class);

        $this->selectMock = $this->createMock(Select::class);

        $this->connectionMock = $this->getMockForAbstractClass(AdapterInterface::class);

        $this->resourceMock = $this->createMock(ResourceConnection::class);

        $this->resourceMock->expects($this->any())
            ->method('getConnection')
            ->willReturn($this->connectionMock);

        $this->resourceMock->expects($this->any())
            ->method('getTableName')
            ->willReturn('sales_order');

        $this->customerInterfaceFactoryMock = $this->getMockBuilder(CustomerInterfaceFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->dataObjectHelperMock = $this->getMockBuilder(DataObjectHelper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->subscriberFactoryMock = $this->getMockBuilder(SubscriberFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->customerRepositoryInterfaceMock = $this->getMockBuilder(CustomerRepositoryInterface::class)
            ->getMockForAbstractClass();

        $this->orderRepositoryInterfaceMock = $this->createMock(OrderRepositoryInterface::class);

        $this->inlineTranslationMock = $this->getMockBuilder(StateInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $this->transportBuilderMock = $this->getMockBuilder(TransportBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->escaperMock = $this->createMock(Escaper::class);

        $this->currencyFactoryMock = $this->getMockBuilder(CurrencyFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->scopeConfigMock = $this->getMockBuilder(ScopeConfigInterface::class)
            ->getMockForAbstractClass();

        $this->scopeConfigMock->expects($this->any())
            ->method('isSetFlag')
            ->willReturn(true);

        $this->contextMock->expects($this->any())
            ->method('getScopeConfig')
            ->willReturn($this->scopeConfigMock);

        $this->helper = new Order(
            $this->contextMock,
            $this->loggerMock,
            $this->resourceMock,
            $this->customerInterfaceFactoryMock,
            $this->dataObjectHelperMock,
            $this->subscriberFactoryMock,
            $this->customerRepositoryInterfaceMock,
            $this->orderRepositoryInterfaceMock,
            $this->inlineTranslationMock,
            $this->transportBuilderMock,
            $this->escaperMock,
            $this->currencyFactoryMock
        );
    }

    /*
    public function testSendTransactionalEmail()
    {

    }
    
    public function testSetOrderOlderThan()
    {

    }

    public function testGetReport()
    {

    }

    public function testIsCronEnabledFromConfig()
    {

    }

    public function testGetOrderById()
    {

    }

    public function testGetQuery()
    {

    }

    public function testGetIsEnabled()
    {

    }

    public function testSetResult()
    {

    }

    public function testGetLimit()
    {

    }

    public function testGetSubscriber()
    {

    }

    public function testSetReport()
    {

    }

    public function testSendReminder()
    {

    }

    public function testGetLimitFromConfig()
    {

    }

    public function testGetSelection()
    {

    }

    public function testGetResult()
    {

    }

    public function testGetCustomerById()
    {

    }

    public function testGetOrderOlderThanFromConfig()
    {

    }

    public function testGetEmailTemplate()
    {

    }

    public function testLoadByEmail()
    {

    }

    public function testGetEmailIdentity()
    {

    }

    public function testGetStartTime()
    {

    }

    public function testSetLimit()
    {

    }
    */

    public function testGetReport()
    {
        $var = [];
        $this->helper->setReport($var);
        $this->assertEquals($var, $this->helper->getReport());
    }

    public function testGetResult()
    {
        $var = [];
        $this->helper->setResult($var);
        $this->assertEquals($var, $this->helper->getResult());
    }

    public function testGetLimit()
    {
        $var = 200;
        $this->helper->setLimit($var);
        $this->assertEquals($var, $this->helper->getLimit());
    }

    public function testGetStartTime()
    {
        $var = 1234567;
        $this->helper->setStartTime($var);
        $this->assertEquals($var, $this->helper->getStartTime());
    }

    public function testGetOrderOlderThan()
    {
        $var = 7;
        $this->helper->setOrderOlderThan($var);
        $this->assertEquals($var, $this->helper->getOrderOlderThan());
    }

    public function testIsEnabledFromConfig()
    {
        $this->assertEquals(true, $this->helper->isEnabledFromConfig());
    }

    public function testIsCronEnabledFromConfig()
    {
        $this->assertEquals(true, $this->helper->isCronEnabledFromConfig());
    }

    public function testGetTableName()
    {
        $this->assertEquals('sales_order', $this->helper->getTableName());
    }

    public function testGetEmailTemplate()
    {
        $var = 'general';
        $this->scopeConfigMock->expects($this->any())
            ->method('getValue')
            ->with(Order::CONFIG_XML_EMAIL_TEMPLATE, ScopeInterface::SCOPE_STORE)
            ->will($this->returnValue($var));

        $this->assertEquals($var, $this->helper->getEmailTemplate());
    }

    public function testGetSelect()
    {
        $this->helper->setSelect($this->createMock(Select::class));
        $this->assertInstanceOf(Select::class, $this->helper->getSelect());
    }
    
    public function testInitiate()
    {
        $this->helper->initiate();
        $this->assertEquals(true, $this->helper->getIsEnabled());
    }

    public function testSetIsEnabled()
    {
        $this->helper->initiate();
        $this->helper->setIsEnabled(false);
        $this->assertEquals(false, $this->helper->getIsEnabled());
    }
    
    public function testSetIsCronEnabled()
    {
        $this->helper->setIsCronEnabled(false);
        $this->assertEquals(false, $this->helper->getIsCronEnabled());
    }

    /*
    // Several configs loaded in initiate()
    public function testIsDisabled()
    {
        $this->scopeConfigMock->expects($this->any())
            ->method('getValue')
            ->with(Order::CONFIG_XML_ENABLED, ScopeInterface::SCOPE_STORE)
            ->will($this->returnValue(false));
        $this->helper->initiate();
    }
    */

    /*
    // Factory method doesn't exist in isolation
    public function testFormatPrice()
    {
        $this->assertEquals('£2.23', $this->helper->formatPrice((float) 2.229, '£'));
    }
    */
}
