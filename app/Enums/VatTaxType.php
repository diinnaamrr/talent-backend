<?php

namespace App\Enums;

enum VatTaxType: string
{
    case PRODUCTBASE = 'product base';
    case ORDERBASE = 'order base';
}
