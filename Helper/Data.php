<?php 
namespace Magecomp\Ajaxlogin\Helper;

use Magento\Store\Model\ScopeInterface;

/**
 * Class Data
 * Magecomp\Ajaxlogin\Helper
 */
class Data extends \Magento\Framework\App\Helper\AbstractHelper{

    protected $scopeConfig;
    const PATH_CONFIG_VALUE = 'magecompajaxlogin/ajaxlogin_general/ajaxlogin_enable';

	public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ){
        $this->scopeConfig = $scopeConfig;
    }

    public function isEnable() {
        return $this->scopeConfig->getValue(self::PATH_CONFIG_VALUE,ScopeInterface::SCOPE_STORE);
    }
}