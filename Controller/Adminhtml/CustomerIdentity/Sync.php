<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Controller\Adminhtml\CustomerIdentity;

use Gtstudio\Ebizcharge\Logger\Logger;
use Gtstudio\Ebizcharge\Service\CustomerIdentityManager;
use Gtstudio\Ebizcharge\Service\CustomerPayloadBuilder;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Store\Model\StoreManagerInterface;

class Sync extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Gtstudio_Ebizcharge::customer_identity_manage';

    public function __construct(
        Context $context,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerPayloadBuilder $payloadBuilder,
        private readonly CustomerIdentityManager $identityManager,
        private readonly StoreManagerInterface $storeManager,
        private readonly Logger $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $customerId = (int) $this->getRequest()->getParam('customer_id');
        /** @var Redirect $result */
        $result = $this->resultRedirectFactory->create();
        $result->setPath('customer/index/edit', ['id' => $customerId]);

        try {
            $customer = $this->customerRepository->getById($customerId);
            $storeId = (int) $this->storeManager
                ->getWebsite((int) $customer->getWebsiteId())
                ->getDefaultStore()
                ->getId();
            $identity = $this->identityManager->sync(
                $customerId,
                $this->payloadBuilder->fromCustomer($customer),
                $storeId
            );
            $this->messageManager->addSuccessMessage(__(
                'The EBizCharge customer identity was verified. Customer ID: %1.',
                $identity->customerId
            ));
        } catch (\Throwable $e) {
            $this->logger->warning('customer_identity.admin_sync_failed', [
                'magento_customer_id' => $customerId,
                'reason' => $e::class,
            ]);
            $this->messageManager->addErrorMessage(
                __('The EBizCharge customer identity could not be verified. Check the gateway debug trace.')
            );
        }

        return $result;
    }
}
