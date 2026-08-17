<?php

namespace App\Enums;

enum DisputeStatus: string
{
    /*
     * Canonical v1 API/storage value remains "open" for
     * backwards compatibility.
     *
     * The case name stays UnderReview because that is the
     * richer workflow meaning used by the enhanced backend
     * and the Figma-facing UI.
     */
    case UnderReview =
        'open';

    case Resolved =
        'resolved';

    case Closed =
        'closed';

    /*
     * Source-level compatibility for older PHP code/tests
     * that referenced DisputeStatus::Open.
     */
    public const Open =
        self::UnderReview;

    public function workflowValue(): string
    {
        return match ($this) {
            self::UnderReview =>
                'under_review',

            self::Resolved =>
                'resolved',

            self::Closed =>
                'closed',
        };
    }
}