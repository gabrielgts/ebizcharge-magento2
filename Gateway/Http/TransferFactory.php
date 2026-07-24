<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Gateway\Http;

use Magento\Payment\Gateway\Http\TransferBuilder;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Http\TransferInterface;

class TransferFactory implements TransferFactoryInterface
{
    public function __construct(private readonly TransferBuilder $transferBuilder)
    {
    }

    public function create(array $request): TransferInterface
    {
        $headers = [];
        if (isset($request['__headers']) && is_array($request['__headers'])) {
            $headers = $request['__headers'];
            unset($request['__headers']);
        }

        return $this->transferBuilder
            ->setBody($request)
            ->setHeaders($headers)
            ->build();
    }
}
