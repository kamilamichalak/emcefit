<?php

namespace App\Domain\Memberships\Enums;

/**
 * Membership mode — spec section 3/4.
 */
enum MembershipMode: string
{
    case Closed = 'zamkniety';         // closed membership with a reserved fixed spot
    case Open = 'otwarty';             // package of X entries, no guaranteed fixed spot
    case Unlimited = 'bez_limitu';     // monthly, unlimited number of entries
    case SingleEntry = 'jednorazowe';  // single entry / extra training session
}
