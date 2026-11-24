<?php

namespace App\Enums;

enum PointTransactionType: string
{
    case Escrow = 'escrow';
    case Release = 'release';
    case Refund = 'refund';
    case Bonus = 'bonus';
}
