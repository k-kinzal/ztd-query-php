<?php

declare(strict_types=1);

namespace SqlFaker\Fuzz\Target;

/**
 * Separates native acceptance from semantic rejection and incomplete verification.
 */
enum VerificationResult: string
{
    case Accepted = 'accepted';
    case Rejected = 'semantic-rejection';
    case Incomplete = 'verification-incomplete';
}
