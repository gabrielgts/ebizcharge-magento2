<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Controller\Adminhtml\DebugTrace;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Gtstudio_Ebizcharge::debug_trace_view';

    public function __construct(Context $context, private readonly PageFactory $resultPageFactory)
    {
        parent::__construct($context);
    }

    public function execute(): ResultInterface|ResponseInterface
    {
        $page = $this->resultPageFactory->create();
        $page->setActiveMenu('Gtstudio_Ebizcharge::debug_traces');
        $page->getConfig()->getTitle()->prepend(__('EBizCharge Debug Traces'));
        return $page;
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed(self::ADMIN_RESOURCE);
    }
}
