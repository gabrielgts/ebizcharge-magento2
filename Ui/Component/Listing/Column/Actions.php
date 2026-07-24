<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Ui\Component\Listing\Column;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Inline row actions in the subscription grid.
 *
 * Available actions depend on status: paused subscriptions show "Resume" not "Pause", etc.
 */
class Actions extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        foreach ($dataSource['data']['items'] as &$row) {
            $id = (int) ($row['entity_id'] ?? 0);
            $status = (string) ($row['_status_raw'] ?? $row['status'] ?? '');
            $row[$this->getData('name')] = $this->buildActions($id, $status);
        }
        unset($row);
        return $dataSource;
    }

    /** @return array<string,array<string,mixed>> */
    private function buildActions(int $id, string $status): array
    {
        $actions = [];

        $actions['view'] = [
            'href' => $this->urlBuilder->getUrl('gtstudio_ebizcharge/subscription/edit', ['id' => $id]),
            'label' => __('View'),
        ];

        $isTerminal = in_array(
            $status,
            [
                SubscriptionInterface::STATUS_CANCELLED,
                SubscriptionInterface::STATUS_COMPLETED,
                SubscriptionInterface::STATUS_EXPIRED,
            ],
            true
        );

        if (!$isTerminal) {
            if ($status === SubscriptionInterface::STATUS_PAUSED
                || $status === SubscriptionInterface::STATUS_FAILING) {
                $actions['resume'] = [
                    'href' => $this->urlBuilder->getUrl('gtstudio_ebizcharge/subscription/resume', ['id' => $id]),
                    'label' => __('Resume'),
                ];
            } else {
                $actions['pause'] = [
                    'href' => $this->urlBuilder->getUrl('gtstudio_ebizcharge/subscription/pause', ['id' => $id]),
                    'label' => __('Pause'),
                ];
            }

            if ($status === SubscriptionInterface::STATUS_ACTIVE) {
                $actions['charge_now'] = [
                    'href' => $this->urlBuilder->getUrl('gtstudio_ebizcharge/subscription/chargeNow', ['id' => $id]),
                    'label' => __('Charge Now'),
                    'confirm' => [
                        'title' => __('Charge Now'),
                        'message' => __('Force an immediate charge for subscription #%1?', $id),
                    ],
                ];
            }

            $actions['cancel'] = [
                'href' => $this->urlBuilder->getUrl('gtstudio_ebizcharge/subscription/cancel', ['id' => $id]),
                'label' => __('Cancel'),
                'confirm' => [
                    'title' => __('Cancel Subscription'),
                    'message' => __('Cancel subscription #%1? This is permanent.', $id),
                ],
            ];
        }

        return $actions;
    }
}
