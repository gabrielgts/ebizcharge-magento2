<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Controller\Subscription;

use Magento\Customer\Controller\AbstractAccount;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

class Index extends AbstractAccount implements HttpGetActionInterface
{
    public function __construct(Context $context, private readonly PageFactory $pageFactory)
    {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('My Subscriptions'));
        return $page;
    }
}
