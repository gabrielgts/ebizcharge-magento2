<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Controller\Adminhtml\Subscription;

use Gtstudio\Ebizcharge\Api\SubscriptionLifecycleInterface;
use Gtstudio\Ebizcharge\Controller\Adminhtml\Subscription as SubscriptionAction;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Shared logic for single-row actions (pause/resume/cancel/charge_now/update_payment_method).
 *
 * Subclasses define {@see doAction()}. The abstract handles param parsing, ACL, error/success
 * messages, and redirect home.
 */
abstract class AbstractAction extends SubscriptionAction
{
    public const ADMIN_RESOURCE = self::ADMIN_RESOURCE_MANAGE;

    public function __construct(
        Context $context,
        protected readonly SubscriptionLifecycleInterface $lifecycle
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface|ResponseInterface
    {
        $id = (int) $this->getRequest()->getParam('id');
        if ($id === 0) {
            $this->messageManager->addErrorMessage(__('Subscription ID is required.'));
            return $this->redirectIndex();
        }

        try {
            $this->doAction($id);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->messageManager->addExceptionMessage($e, __('An error occurred while updating the subscription.'));
        }

        return $this->redirectIndex();
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed(self::ADMIN_RESOURCE);
    }

    abstract protected function doAction(int $subscriptionId): void;

    protected function redirectIndex(): Redirect
    {
        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $redirect->setPath('gtstudio_ebizcharge/subscription/index');
        return $redirect;
    }
}
