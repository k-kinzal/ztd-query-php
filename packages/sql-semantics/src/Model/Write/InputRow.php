<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * An ordered write row whose slots read an expression or the destination's default.
 * @visibility public
 */
final class InputRow
{
    /**
     * @param list<Expression|DefaultSource> $items Positional storage inputs
     * @throws InvalidStructure
     */
    public function __construct(public readonly Dialect $dialect, public readonly array $items)
    {
        Collections::alternatives($items, [Expression::class, DefaultSource::class]);
        if ($items === [] && $dialect !== Dialect::MySql) {
            throw new InvalidStructure('This database requires a nonempty input row.');
        }
        foreach ($items as $value) {
            if ($value instanceof Expression && $value->type->dialect !== $dialect) {
                throw new InvalidStructure('A write row must retain one SQL dialect.');
            }
            if ($value instanceof DefaultSource && $dialect === Dialect::Sqlite) {
                throw new InvalidStructure('SQLite uses DEFAULT VALUES for a default insertion.');
            }
        }
    }
}
