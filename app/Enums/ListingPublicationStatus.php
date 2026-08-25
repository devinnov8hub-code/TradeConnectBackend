<?php

namespace App\Enums;

enum ListingPublicationStatus: string
{
    case Pending = 'pending';
    case Live = 'live';
    case Inactive = 'inactive';
}