<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Open = 'open';
    case Full = 'full';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
