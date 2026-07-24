<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;

class ResponseValidator extends AbstractValidator
{
    public const RESULT_APPROVED = 'A';
    public const RESULT_DECLINED = 'D';
    public const RESULT_ERROR = 'E';

    public function validate(array $validationSubject): ResultInterface
    {
        $response = $validationSubject['response'] ?? [];
        if (!is_array($response)) {
            return $this->createResult(false, [__('Empty gateway response.')]);
        }

        $code = (string) ($response['ResultCode'] ?? '');
        if ($code === self::RESULT_APPROVED) {
            return $this->createResult(true);
        }

        $errors = [];
        if ($code === self::RESULT_DECLINED) {
            $errors[] = __('Your card was declined: %1', (string) ($response['Error'] ?? 'declined'));
        } else {
            $errors[] = __('Payment processing error: %1', (string) ($response['Error'] ?? 'unknown error'));
        }
        return $this->createResult(false, $errors);
    }
}
