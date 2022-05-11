<?php 
namespace Magecomp\Ajaxlogin\Controller\Account;

use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\InputException;
use Magento\Customer\Model\CustomerExtractor;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Model\Session;
use Magento\Customer\Model\Account\Redirect as AccountRedirect;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Framework\UrlFactory;

class Createpostnew extends \Magento\Framework\App\Action\Action
{
    protected $resultJsonFactory;
     /**
     * @var Validator
     */
    private $formKeyValidator;
    /**
     * @var \Magento\Customer\Model\CustomerExtractor
     */
    protected $customerExtractor;
    /**
     * @var \Magento\Customer\Api\AccountManagementInterface
     */
    protected $accountManagement;
    /**
     * @var Session
     */
    protected $session;
    /**
     * @var AccountRedirect
     */
    private $accountRedirect;
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;
    /**
     * @var \Magento\Framework\Stdlib\Cookie\PhpCookieManager
     */
    private $cookieMetadataManager;
    /**
     * @var \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory
     */
    private $cookieMetadataFactory;
     /**
     * @var \Magento\Customer\Model\Url
     */
    protected $customerUrl;
    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlModel;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        CustomerExtractor $customerExtractor,
        AccountManagementInterface $accountManagement,
        Session $customerSession,
        AccountRedirect $accountRedirect,
        ScopeConfigInterface $scopeConfig,
        CustomerUrl $customerUrl,
        UrlFactory $urlFactory,
        Validator $formKeyValidator = null
    ){
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->customerExtractor = $customerExtractor;
        $this->accountManagement = $accountManagement;
        $this->session = $customerSession;
        $this->accountRedirect = $accountRedirect;
        $this->scopeConfig = $scopeConfig;
        $this->customerUrl = $customerUrl;
        $this->urlModel = $urlFactory->create();
        $this->formKeyValidator = $formKeyValidator ?: ObjectManager::getInstance()->get(Validator::class);
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
            $this->cookieMetadataManager = ObjectManager::getInstance()->get(
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
            $this->cookieMetadataFactory = ObjectManager::getInstance()->get(
                \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory::class
            );
        }
        return $this->cookieMetadataFactory;
    }

    public function execute(){
        $resultRedirect = $this->resultRedirectFactory->create();
        if (!$this->getRequest()->isPost() || !$this->formKeyValidator->validate($this->getRequest())
        ) {
            $response = [
                'errors' => true,
                'message' => __('Your account cannot be created')
            ];
        }
        else{
            try {

                $customer = $this->customerExtractor->extract('customer_account_create', $this->_request);
                $password = $this->getRequest()->getParam('password');
                $confirmation = $this->getRequest()->getParam('password_confirmation');
                if ($password != $confirmation) {                   
                    throw new InputException(__('Please make sure your passwords match.'));
                }
                $customer = $this->accountManagement->createAccount($customer, $password);
                $this->_eventManager->dispatch(
                    'customer_register_success',
                    ['account_controller' => $this, 'customer' => $customer]
                );
                $confirmationStatus = $this->accountManagement->getConfirmationStatus($customer->getId());
                if ($confirmationStatus === AccountManagementInterface::ACCOUNT_CONFIRMATION_REQUIRED) {
                    $response = [
                        'errors' => false,
                        'message' => 'Your Account is not confirmed yet, Check mail!'
                    ];
                    $this->messageManager->addComplexSuccessMessage(
                        'confirmAccountSuccessMessage',
                        [
                            'url' => $this->customerUrl->getEmailConfirmationUrl($customer->getEmail()),
                        ]
                    );
                    $url = $this->urlModel->getUrl('*/*/index', ['_secure' => true]);
                    $resultRedirect->setUrl($this->_redirect->success($url));
                }
                else{
                    $this->session->setCustomerDataAsLoggedIn($customer);
                    $requestedRedirect = $this->accountRedirect->getRedirectCookie();
                    if (!$this->scopeConfig->getValue('customer/startup/redirect_dashboard') && $requestedRedirect) {
                        $resultRedirect->setUrl($this->_redirect->success($requestedRedirect));
                        $this->accountRedirect->clearRedirectCookie();
                        return $resultRedirect;
                    }
                    $response = [
                        'errors' => false
                    ];
                    $resultRedirect = $this->accountRedirect->getRedirect();
                    
                }
                if ($this->getCookieManager()->getCookie('mage-cache-sessid')) {
                    $metadata = $this->getCookieMetadataFactory()->createCookieMetadata();
                    $metadata->setPath('/');
                    $this->getCookieManager()->deleteCookie('mage-cache-sessid', $metadata);
                }
            }
            catch (InputException $e) {
                $response = [
                    'errors' => true,
                    'message' => 'password not match with confirm password!!'
                ];
            }
            catch (\Exception $e) {
                $response = [
                    'errors' => true,
                    'message' => 'We can\'t save the customer! '
                ];
            }
        }

        $resultJson = $this->resultJsonFactory->create();
        return $resultJson->setData($response);
    }
}
