<?php

namespace App\Enums;

enum OrderStatus: string
{
    case New = 'new';
    case InTransit = 'in_transit';
    case Cancelled = 'cancelled';
    case Delivered = 'delivered';
}
