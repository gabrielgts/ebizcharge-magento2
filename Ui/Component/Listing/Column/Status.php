<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Ui\Component\Listing\Column;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Renders the status as a colored pill so the grid surfaces failing subscriptions visually.
 *
 * Uses Magento_Ui's built-in `data-grid-cellLabel` styles via inline color hints; admins skim
 * for red/green and only filter for details.
 */
class Status extends Column
{
    private const COLORS = [
        SubscriptionInterface::STATUS_ACTIVE => '#79a22e',     // green
        SubscriptionInterface::STATUS_PAUSED => '#1979c3',     // blue
        SubscriptionInterface::STATUS_FAILING => '#e02b27',    // red
        SubscriptionInterface::STATUS_CANCELLED => '#7d7d7d',  // grey
        SubscriptionInterface::STATUS_EXPIRED => '#7d7d7d',    // grey
        SubscriptionInterface::STATUS_COMPLETED => '#b8a300',  // gold
    ];

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
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
        $field = $this->getData('name');
        foreach ($dataSource['data']['items'] as &$row) {
            $status = (string) ($row[$field] ?? '');
            $row['_status_raw'] = $status;
            $color = self::COLORS[$status] ?? '#666';
            $label = ucfirst($status);
            $row[$field] = sprintf(
                '<span style="display:inline-block;padding:2px 10px;border-radius:10px;background:%s;color:#fff;font-weight:600;font-size:11px;">%s</span>',
                htmlspecialchars($color, ENT_QUOTES),
                htmlspecialchars($label, ENT_QUOTES)
            );
        }
        unset($row);
        return $dataSource;
    }
}
