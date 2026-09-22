<?php

declare(strict_types=1);

namespace SqlSemantics\Type\Identity;

use Override;

/**
 * A PostgreSQL interval with a field range and optional fractional precision.
 * @visibility public
 */
final class IntervalStorage implements TypeIdentity
{
    /**

     */
    public function __construct(
        public readonly IntervalFields $fields = IntervalFields::All,
        public readonly ?Numeric\NumericParameter $precision = null,
    ) {
    }

    #[Override]
    public function name(): string
    {
        return 'interval';
    }
}
