<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Controller\Adminhtml\Subscription;

use Gtstudio\Ebizcharge\Api\SubscriptionRepositoryInterface;
use Gtstudio\Ebizcharge\Controller\Adminhtml\Subscription as SubscriptionAction;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Edit extends SubscriptionAction
{
    public const ADMIN_RESOURCE = self::ADMIN_RESOURCE_VIEW;
    public const REGISTRY_KEY = 'gtstudio_ebizcharge_admin_current_subscription';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
        private readonly Registry $registry
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface|ResponseInterface
    {
        $id = (int) $this->getRequest()->getParam('id');
        if ($id === 0) {
            return $this->redirectIndex();
        }

        try {
            $subscription = $this->subscriptionRepository->getById($id);
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $this->redirectIndex();
        }

        $this->registry->register(self::REGISTRY_KEY, $subscription, true);

        /** @var Page $page */
        $page = $this->pageFactory->create();
        $page->setActiveMenu('Gtstudio_Ebizcharge::subscriptions');
        $page->getConfig()->getTitle()->prepend(__('Subscription #%1', $id));
        return $page;
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed(self::ADMIN_RESOURCE);
    }

    private function redirectIndex(): Redirect
    {
        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $redirect->setPath('gtstudio_ebizcharge/subscription/index');
        return $redirect;
    }
}
