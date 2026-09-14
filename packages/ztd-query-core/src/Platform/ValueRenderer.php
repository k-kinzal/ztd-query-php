<?php

declare(strict_types=1);

namespace ZtdQuery\Platform;

use Stringable;
use ZtdQuery\Schema\ColumnDeclaration;

/**
 * Writes a PHP value as the SQL expression a dialect would read it back from.
 *
 * A shadow row is written into the statement itself, so every value has to
 * survive the round trip as the value it was. What that takes differs by
 * dialect -- what quotes a string, what writes bytes, what a literal's type is
 * taken to be -- which is why each dialect has its own.
 *
 * @phpstan-type RenderableValue bool|float|int|string|Stringable|resource|null
 */
interface ValueRenderer
{
    /**
     * Writes a value as the SQL that reads it back.
     *
     * The column's declaration is what says how the database will read the
     * literal, so where it is known the value is cast to it. Where it is not,
     * the value's own PHP type is all there is to go on.
     *
     * What arrives here is whatever a driver answered or a fixture was
     * written with, which is not something ZTD can promise anything about --
     * so this is where anything no literal could carry is refused.
     *
     * @param mixed $value Value to write, as a driver or a fixture handed it over
     * @param ColumnDeclaration|null $type How the column holding it was declared, or null where nothing declared it
     *
     * @return string SQL reading that value back
     */
    public function renderValue(mixed $value, ?ColumnDeclaration $type = null): string;
}
