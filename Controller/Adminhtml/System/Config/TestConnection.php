<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Controller\Adminhtml\System\Config;

use Gtstudio\Ebizcharge\Service\Gateway\ConnectionProbe;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;

/** Tests saved or unsaved gateway credentials from Admin configuration. */
class TestConnection extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Gtstudio_Ebizcharge::config';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly ConnectionProbe $probe
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface|ResponseInterface
    {
        /** @var Json $result */
        $result = $this->jsonFactory->create();
        $request = $this->getRequest();

        $userId = $this->resolveSecret((string) $request->getParam('user_id', ''));
        $securityId = $this->resolveSecret((string) $request->getParam('security_id', ''));
        $password = $this->resolveSecret((string) $request->getParam('password', ''));
        $endpointMode = (string) $request->getParam('endpoint_mode', '');
        $endpointOverride = (string) $request->getParam('endpoint_url_override', '');

        $outcome = $this->probe->probe(
            userIdOverride: $userId,
            securityIdOverride: $securityId,
            passwordOverride: $password,
            endpointModeOverride: $endpointMode !== '' ? $endpointMode : null,
            endpointUrlOverride: $endpointOverride !== '' ? $endpointOverride : null
        );

        return $result->setData($outcome);
    }

    /** Returns null for Magento's unchanged encrypted-value placeholder. */
    private function resolveSecret(string $value): ?string
    {
        if ($value === '' || preg_match('/^\*+$/', $value)) {
            return null;
        }
        return $value;
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed(self::ADMIN_RESOURCE);
    }
}
