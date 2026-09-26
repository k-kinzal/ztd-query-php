<?php

declare(strict_types=1);

namespace Tests\Fake;

use SqlCatalog\Core\Evaluation\ArrayEntry;
use SqlCatalog\Core\Evaluation\ArrayTerm;
use SqlCatalog\Core\Evaluation\Domain;

/**
 * A custom model that selects two placeholders.
 */
final class PairModel
{
    /**
     * @param list<Domain> $arguments
     */
    public function __invoke(array $arguments): Domain
    {
        return self::evaluate($arguments);
    }

    /**
     * @param list<Domain> $arguments
     */
    public static function evaluate(array $arguments): Domain
    {
        return Domain::of(new ArrayTerm([
            new ArrayEntry(null, Domain::literal('?')),
            new ArrayEntry(null, Domain::literal('?')),
        ]));
    }
}
