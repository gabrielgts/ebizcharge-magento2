<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Controller\Adminhtml\Subscription;

use Gtstudio\Ebizcharge\Controller\Adminhtml\Subscription as SubscriptionAction;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

class Index extends SubscriptionAction
{
    public const ADMIN_RESOURCE = self::ADMIN_RESOURCE_VIEW;

    public function __construct(Context $context, private readonly PageFactory $resultPageFactory)
    {
        parent::__construct($context);
    }

    public function execute(): ResultInterface|ResponseInterface
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Gtstudio_Ebizcharge::subscriptions');
        $resultPage->getConfig()->getTitle()->prepend(__('EBizCharge Subscriptions'));
        return $resultPage;
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed(self::ADMIN_RESOURCE);
    }
}
