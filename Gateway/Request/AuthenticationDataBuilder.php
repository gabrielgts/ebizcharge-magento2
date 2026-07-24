<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Gateway\Request;

use Gtstudio\Ebizcharge\Gateway\Config\Config;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class AuthenticationDataBuilder implements BuilderInterface
{
    public function __construct(private readonly Config $config)
    {
    }

    public function build(array $buildSubject): array
    {
        $payment = SubjectReader::readPayment($buildSubject);
        $order = $payment->getOrder();
        $storeId = $this->extractStoreId($order);

        return [
            'securityToken' => [
                'UserId' => $this->config->getUserId($storeId),
                'SecurityId' => $this->config->getSecurityId($storeId),
                'Password' => $this->config->getPassword($storeId),
            ],
        ];
    }

    private function extractStoreId(?OrderAdapterInterface $order): ?int
    {
        if ($order === null) {
            return null;
        }
        $storeId = $order->getStoreId();
        return $storeId === null ? null : (int) $storeId;
    }
}
