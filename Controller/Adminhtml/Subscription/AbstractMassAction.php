<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Controller\Adminhtml\Subscription;

use Gtstudio\Ebizcharge\Api\SubscriptionLifecycleInterface;
use Gtstudio\Ebizcharge\Controller\Adminhtml\Subscription as SubscriptionAction;
use Gtstudio\Ebizcharge\Model\ResourceModel\Subscription\CollectionFactory;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Ui\Component\MassAction\Filter;

abstract class AbstractMassAction extends SubscriptionAction
{
    public const ADMIN_RESOURCE = self::ADMIN_RESOURCE_MANAGE;

    public function __construct(
        Context $context,
        protected readonly Filter $filter,
        protected readonly CollectionFactory $collectionFactory,
        protected readonly SubscriptionLifecycleInterface $lifecycle
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface|ResponseInterface
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $ok = 0;
        $failed = 0;
        $errors = [];

        foreach ($collection as $row) {
            try {
                $this->actOn((int) $row->getEntityId());
                $ok++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[(int) $row->getEntityId()] = $e->getMessage();
            }
        }

        if ($ok > 0) {
            $this->messageManager->addSuccessMessage(
                __('%1 — %2 subscription(s) updated.', $this->successLabel(), $ok)
            );
        }
        if ($failed > 0) {
            foreach ($errors as $id => $msg) {
                $this->messageManager->addErrorMessage(__('Subscription #%1: %2', $id, $msg));
            }
        }

        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $redirect->setPath('gtstudio_ebizcharge/subscription/index');
        return $redirect;
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed(self::ADMIN_RESOURCE);
    }

    abstract protected function actOn(int $subscriptionId): void;

    abstract protected function successLabel(): string;
}
