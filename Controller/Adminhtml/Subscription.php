<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Controller\Adminhtml;

use Magento\Backend\App\Action;

abstract class Subscription extends Action
{
    public const ADMIN_RESOURCE_VIEW = 'Gtstudio_Ebizcharge::subscription_view';
    public const ADMIN_RESOURCE_MANAGE = 'Gtstudio_Ebizcharge::subscription_manage';
}
