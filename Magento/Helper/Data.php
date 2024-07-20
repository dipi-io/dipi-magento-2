<?php

namespace Dipi\Magento\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    /**
     * @param string $field
     * @param string $storeCode
     * @return mixed
     */
    public function getConfigValue($field, $storeCode = null)
    {
        return $this->scopeConfig->getValue(
            $field,
            ScopeInterface::SCOPE_STORE,
            $storeCode
        );
    }
}
