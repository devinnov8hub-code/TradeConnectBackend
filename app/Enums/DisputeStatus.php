<?php

namespace App\Enums;

enum DisputeStatus: string
{
    case UnderReview =
        'under_review';

    case Resolved =
        'resolved';

    case Closed =
        'closed';
}