<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Service;

/** Order-payment additional-information keys used for ERP reconciliation and support audits. */
class CustomerIdentityMetadata
{
    public const CUSTOMER_ID = 'gtstudio_ebizcharge_customer_id';
    public const CUSTOMER_INTERNAL_ID = 'gtstudio_ebizcharge_customer_internal_id';
    public const CUSTOMER_NUMBER = 'gtstudio_ebizcharge_customer_number';
    public const STATUS = 'gtstudio_ebizcharge_customer_mapping_status';
}
