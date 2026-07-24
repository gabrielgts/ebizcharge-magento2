<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Controller\Subscription;

use Gtstudio\Ebizcharge\Api\SubscriptionRepositoryInterface;
use Magento\Customer\Controller\AbstractAccount;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;

class View extends AbstractAccount implements HttpGetActionInterface
{
    public const REGISTRY_KEY = 'gtstudio_ebizcharge_current_subscription';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
        private readonly CustomerSession $customerSession,
        private readonly Registry $registry
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $id = (int) $this->getRequest()->getParam('id');
        if ($id === 0) {
            return $this->forwardNoRoute();
        }

        try {
            $subscription = $this->subscriptionRepository->getById($id);
        } catch (\Throwable) {
            return $this->forwardNoRoute();
        }

        if ($subscription->getCustomerId() !== (int) $this->customerSession->getCustomerId()) {
            return $this->forwardNoRoute();
        }

        $this->registry->register(self::REGISTRY_KEY, $subscription, true);

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Subscription #%1', $id));
        return $page;
    }

    private function forwardNoRoute(): Forward
    {
        /** @var Forward $forward */
        $forward = $this->resultFactory->create(ResultFactory::TYPE_FORWARD);
        $forward->forward('noroute');
        return $forward;
    }
}
