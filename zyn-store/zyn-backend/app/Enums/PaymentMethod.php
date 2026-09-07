<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Khqr = 'khqr';
    case PayWay = 'payway';
    case CashOnDelivery = 'cod';
}
