<?php

namespace App\Enums;

enum ProductStatus: string
{
    case Available = 'available';
    case Sample = 'sample';
    case Draft = 'draft';
}
