<?php

namespace Dipi\Magento\Model;

use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\App\RequestInterface;
use Dipi\Magento\Api\DipiInterface;
use Magento\SalesRule\Model\RuleFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Psr\Log\LoggerInterface;
use Dipi\Magento\Helper\Data;
use Magento\Customer\Model\ResourceModel\Group\Collection as GroupCollection;

class DipiApi implements DipiInterface
{
    protected $_storeManager;
    protected $_request;
    protected $_scopeConfig;
    protected $_timezoneInterface;
    protected $_ruleFactory;
    protected $_logger;
    protected $_helper;
    protected $_groupCollection;

    public function __construct(
        StoreManagerInterface $storeManager,
        RequestInterface $request,
        RuleFactory $ruleFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        TimezoneInterface $timezoneInterface,
        LoggerInterface $logger,
        Data $helper,
        GroupCollection $groupCollection
    ) {
        $this->_storeManager = $storeManager;
        $this->_request = $request;
        $this->_ruleFactory = $ruleFactory;
        $this->_scopeConfig = $scopeConfig;
        $this->_timezoneInterface = $timezoneInterface;
        $this->_logger = $logger;
        $this->_helper = $helper;
        $this->_groupCollection = $groupCollection;
    }

    public function CreateCoupon()
    {
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/dipi.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info("Coupon Request received");

        $enabled = $this->_helper->getConfigValue('dipi_configuration/general/enabled') ?? false;
        $authenticationToken = $this->_helper->getConfigValue('dipi_configuration/general/magento_token');
        if ($enabled && $authenticationToken!='') {
            $token = '';
            foreach (getallheaders() as $name => $value) {
                if ($name == 'Authorization') {
                    $token = $value;
                }
            }

            $token = explode(' ', $token);
            if (!isset($token[1]) || $token[1] != $authenticationToken) {
                $info[] = ['Status' => 'Access Denied'];
                return $info;
            }
        }

        $postData = json_decode(file_get_contents('php://input'), true);
        $couponCode = $postData['coupon']['primary'];
        $secondaryCouponCode = $postData['coupon']['secondary'];
        $randomCouponCode = $this->generateRandomString(8);
        $discount_amount = $postData['coupon']['percent'];
        $store = $this->_storeManager->getStore();
        $websiteId = array($this->_storeManager->getStore()->getWebsiteId());

        if ($this->createRule($couponCode, $discount_amount, $websiteId, $store)) {
            $info[] = ['Status' => 'Coupon '.$couponCode.' created successfully.'];
        } else if ($this->createRule($secondaryCouponCode, $discount_amount, $websiteId, $store)) {
            $info[] = ['Status' => 'Coupon '.$secondaryCouponCode.' created successfully.'];
        } else if ($this->createRule($randomCouponCode, $discount_amount, $websiteId, $store)) {
            $info[] = ['Status' => 'Coupon '.$randomCouponCode.' created successfully.'];
        } else {
            $info[] = ['Status' => 'Coupon creation failed.'];
        }

        return $info;
    }

    public function createRule($couponCode, $discount_amount, $websiteId, $store)
    {
        try {
            $coupon = [
                'name' => 'Dipi Coupon',
                'desc' => 'Dipi Coupon',
                'start' => $this->_timezoneInterface->date()->format('Y-m-d'),
                'end' => date('Y-m-d', strtotime('+12 months')),
                'discount_type' => 'by_percent',
                'discount_amount' => $discount_amount,
                'flag_is_free_shipping' => 'no',
            ];

            $customerGroupIds = $this->_groupCollection->getAllIds();

            $shoppingCartPriceRule = $this->_ruleFactory->create();
            $shoppingCartPriceRule->setName($coupon['name'])
                ->setDescription($coupon['desc'])
                ->setFromDate($coupon['start'])
                ->setToDate($coupon['end'])
                ->setUsesPerCustomer(0)
                ->setCustomerGroupIds($customerGroupIds)
                ->setIsActive(1)
                ->setSimpleAction($coupon['discount_type'])
                ->setDiscountAmount($coupon['discount_amount'])
                ->setApplyToShipping($coupon['flag_is_free_shipping'])
                ->setWebsiteIds($websiteId)
                ->setCouponType(2)
                ->setCouponCode($couponCode)
                ->setUsesPerCoupon(0);
            $shoppingCartPriceRule->save();

            return true;
        } catch (\Exception $exception) {
            $this->_logger->error($exception->getMessage());
            return false;
        }
    }

    public function generateRandomString($length) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    } 
}
