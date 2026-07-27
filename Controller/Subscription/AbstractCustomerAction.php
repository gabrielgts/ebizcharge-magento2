<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Controller\Subscription;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;
use Gtstudio\Ebizcharge\Api\SubscriptionLifecycleInterface;
use Gtstudio\Ebizcharge\Api\SubscriptionRepositoryInterface;
use Magento\Customer\Controller\AbstractAccount;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/** Provides authenticated customer subscription actions. */
abstract class AbstractCustomerAction extends AbstractAccount implements
    HttpPostActionInterface,
    CsrfAwareActionInterface
{
    public function __construct(
        Context $context,
        protected readonly CustomerSession $customerSession,
        protected readonly SubscriptionRepositoryInterface $subscriptionRepository,
        protected readonly SubscriptionLifecycleInterface $lifecycle,
        protected readonly FormKeyValidator $formKeyValidator
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        if (!$this->formKeyValidator->validate($this->getRequest())) {
            $this->messageManager->addErrorMessage(__('Invalid form key. Please try again.'));
            return $this->redirectIndex();
        }

        $id = (int) $this->getRequest()->getParam('id');
        if ($id === 0) {
            $this->messageManager->addErrorMessage(__('Subscription ID is required.'));
            return $this->redirectIndex();
        }

        try {
            $subscription = $this->loadOwnSubscription($id);
            $this->doAction($subscription);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->messageManager->addExceptionMessage($e, __('An error occurred while updating your subscription.'));
        }

        return $this->redirectIndex();
    }

    /** Loads a customer-owned subscription. */
    protected function loadOwnSubscription(int $id): SubscriptionInterface
    {
        $subscription = $this->subscriptionRepository->getById($id);
        $customerId = (int) $this->customerSession->getCustomerId();
        if ($subscription->getCustomerId() !== $customerId) {
            throw new AuthorizationException(__('This subscription does not belong to you.'));
        }
        return $subscription;
    }

    abstract protected function doAction(SubscriptionInterface $subscription): void;

    protected function redirectIndex(): Redirect
    {
        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $redirect->setPath('gtstudio_ebizcharge/subscription/index');
        return $redirect;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return $this->formKeyValidator->validate($request);
    }
}
