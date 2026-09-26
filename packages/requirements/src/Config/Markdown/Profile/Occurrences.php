<?php

declare(strict_types=1);

namespace Requirements\Config\Markdown\Profile;

use Requirements\Input\InvalidInputException;

/**
 * Checks the minContains and maxContains constraints of the profile.
 */
final class Occurrences
{
    /**
     * Checks a count; minContains defaults to 1 and maxContains to unbounded.
     *
     * @param int $count The number of occurrences
     * @param array<string, mixed> $schema The constrained profile entry
     * @param string $context The name used in the error message
     *
     * @throws InvalidInputException When the bounds are not integers or the count lies outside them
     */
    public static function check(int $count, array $schema, string $context): void
    {
        $minimum = $schema['minContains'] ?? 1;
        $maximum = $schema['maxContains'] ?? PHP_INT_MAX;
        if (!is_int($minimum) || !is_int($maximum) || $count < $minimum || $count > $maximum) {
            throw new InvalidInputException("$context: document-schema occurrence constraint failed.");
        }
    }
}
