<?php 
namespace Magecomp\Ajaxlogin\Controller\Account;


use Magento\Framework\App\Action\Context;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Customer\Model\Session;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Model\Account\Redirect as AccountRedirect;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\EmailNotConfirmedException;	
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\Request\InvalidRequestException;

class Loginpopup extends \Magento\Framework\App\Action\Action{
	 protected $resultJsonFactory;
	/**
     * @var \Magento\Framework\Data\Form\FormKey\Validator
     */
    protected $formKeyValidator;
    /**
     * @var Session
     */
    protected $session;
    /**
     * @var \Magento\Customer\Api\AccountManagementInterface
     */
    protected $customerAccountManagement;
    /**
     * @var \Magento\Framework\Stdlib\Cookie\PhpCookieManager
     */
    private $cookieMetadataManager;
    /**
     * @var \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory
     */
    private $cookieMetadataFactory;
    /**
     * @var AccountRedirect
     */
    protected $accountRedirect;
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

	public function __construct(
		Context $context,
		Session $customerSession,
		AccountManagementInterface $customerAccountManagement,
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
		Validator $formKeyValidator,
		AccountRedirect $accountRedirect
	){
		parent::__construct($context);
		$this->formKeyValidator = $formKeyValidator;
		$this->session = $customerSession;
		$this->customerAccountManagement = $customerAccountManagement;
		$this->accountRedirect = $accountRedirect;
		$this->resultJsonFactory = $resultJsonFactory;
	}
	/**
     * Get scope config
     *
     * @return ScopeConfigInterface
     * @deprecated 100.0.10
     */
    private function getScopeConfig()
    {
        if (!($this->scopeConfig instanceof \Magento\Framework\App\Config\ScopeConfigInterface)) {
            return \Magento\Framework\App\ObjectManager::getInstance()->get(
                \Magento\Framework\App\Config\ScopeConfigInterface::class
            );
        } else {
            return $this->scopeConfig;
        }
    }
	/**
     * Retrieve cookie manager
     *
     * @deprecated 100.1.0
     * @return \Magento\Framework\Stdlib\Cookie\PhpCookieManager
     */
    private function getCookieManager()
    {
        if (!$this->cookieMetadataManager) {
            $this->cookieMetadataManager = \Magento\Framework\App\ObjectManager::getInstance()->get(
                \Magento\Framework\Stdlib\Cookie\PhpCookieManager::class
            );
        }
        return $this->cookieMetadataManager;
    }
    /**
     * Retrieve cookie metadata factory
     *
     * @deprecated 100.1.0
     * @return \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory
     */
    private function getCookieMetadataFactory()
    {
        if (!$this->cookieMetadataFactory) {
            $this->cookieMetadataFactory = \Magento\Framework\App\ObjectManager::getInstance()->get(
                \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory::class
            );
        }
        return $this->cookieMetadataFactory;
    }
	public function execute(){

		if ($this->session->isLoggedIn() || !$this->formKeyValidator->validate($this->getRequest())) {
            $response = [
            	'error' => true,
            	'message' => 'the session is logged in or entered data is not valid'
            ];
        }
        
        if ($this->getRequest()->isPost()) {
        	$login = $this->getRequest()->getPost("login");
        	if (!empty($login['username']) && !empty($login['password'])) {
        		try{
	        		$customer = $this->customerAccountManagement->authenticate($login['username'], $login['password']);
	        		$this->session->setCustomerDataAsLoggedIn($customer);
	        		$response = [
		            	'error' => false,
		            	'message' =>  'Logged In!'
		            ];
		            if ($this->getCookieManager()->getCookie('mage-cache-sessid')) {
                        $metadata = $this->getCookieMetadataFactory()->createCookieMetadata();
                        $metadata->setPath('/');
                        $this->getCookieManager()->deleteCookie('mage-cache-sessid', $metadata);
                    }
                    $redirectUrl = $this->accountRedirect->getRedirectCookie();
                    if (!$this->getScopeConfig()->getValue('customer/startup/redirect_dashboard') && $redirectUrl) {
                        $this->accountRedirect->clearRedirectCookie();
                        $resultRedirect = $this->resultRedirectFactory->create();
                        $resultRedirect->setUrl($this->_redirect->success($redirectUrl));
                        return $resultRedirect;
                    }
	        	}
	        	catch (EmailNotConfirmedException $e) {
                   $response = [
		            	'error' => true,
		            	'message' =>  'The Email is not confirmed yet, please check mail and confirm!'
		            ];
                    $this->session->setUsername($login['username']);
                }
	        	catch (AuthenticationException $e) {
	        		$response = [
		            	'error' => true,
		            	'message' =>  'The account sign-in was incorrect or your account is disabled temporarily. '
		            ];
                } 
                catch (\Exception $e) {
                	$response = [
		            	'error' => true,
		            	'message' =>  'An unspecified error occurred. Please contact us for assistance.'
		            ];
                }
        	}
        }
        $this->session->setUsername($login['username']);
        $resultJson = $this->resultJsonFactory->create();
        return $resultJson->setData($response);
    }
}