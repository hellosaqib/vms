<?php
declare(strict_types=1);

const VMS_CURRENCY_CODE = 'GBP';
const VMS_CURRENCY_SYMBOL = '£';
const VMS_CURRENCY_NAME = 'British Pound';

function formatCurrency(
    float $amount,
    bool $showDecimals = true
): string {
    return VMS_CURRENCY_SYMBOL .
        number_format(
            $amount,
            $showDecimals ? 2 : 0,
            '.',
            ','
        );
}
?>