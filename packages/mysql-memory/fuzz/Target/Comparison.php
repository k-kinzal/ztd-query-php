<?php

declare(strict_types=1);

namespace Fuzz\Target;

/**
 * The outcome of comparing a generated statement, distinguishing a volatile reference from a match.
 */
final class Comparison
{
    /**
     * @param bool $volatile Whether the two observations on the real server differed
     * @param list<string> $contracts Validated nondeterministic fields whose allowed values were compared
     * @param string|null $difference The complete difference, or null when there is none to report
     * @param string|null $referenceDifference The difference between the two native observations, when volatile
     */
    public function __construct(public readonly bool $volatile, public readonly ?string $difference = null, public readonly array $contracts = [], public readonly ?string $referenceDifference = null)
    {
    }
}
