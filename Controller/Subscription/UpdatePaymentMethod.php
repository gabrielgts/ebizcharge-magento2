<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Controller\Subscription;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;
use Gtstudio\Ebizcharge\Api\SubscriptionLifecycleInterface;
use Gtstudio\Ebizcharge\Api\SubscriptionRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Vault\Api\PaymentTokenManagementInterface;

class UpdatePaymentMethod extends AbstractCustomerAction
{
    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        SubscriptionRepositoryInterface $subscriptionRepository,
        SubscriptionLifecycleInterface $lifecycle,
        FormKeyValidator $formKeyValidator,
        private readonly PaymentTokenManagementInterface $tokenManagement
    ) {
        parent::__construct(
            $context,
            $customerSession,
            $subscriptionRepository,
            $lifecycle,
            $formKeyValidator
        );
    }

    protected function doAction(SubscriptionInterface $subscription): void
    {
        $publicHash = (string) $this->getRequest()->getParam('public_hash');
        if ($publicHash === '') {
            throw new LocalizedException(__('A payment method must be selected.'));
        }

        $token = $this->tokenManagement->getByPublicHash($publicHash, $subscription->getCustomerId());
        if ($token === null) {
            throw new LocalizedException(__('That payment method is not available.'));
        }

        $this->lifecycle->updatePaymentMethod(
            (int) $subscription->getEntityId(),
            (int) $token->getEntityId()
        );
        $this->messageManager->addSuccessMessage(__('Payment method updated.'));
    }
}
