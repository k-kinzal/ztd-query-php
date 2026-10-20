<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite\Schema;

use SqlFixture\Analysis\NumericLiteral;
use SqlFixture\Schema\TypeShape;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\HexLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Lexical\Word;
use SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain;
use SqlSemantics\Platform\Sqlite\Statement\Type\NumberSign;
use SqlSemantics\Platform\Sqlite\Statement\Type\SignedNumber;
use SqlSemantics\Platform\Sqlite\Statement\Type\TypeName;

/**
 * Reads the declared type of a column and the numbers written after it.
 *
 * The analysis decides the declared type as SQLite records it, which drops a
 * trailing GENERATED ALWAYS. A column without a type has the BLOB affinity and
 * is named BLOB.
 *
 * @visibility root
 */
final class TypeDeclaration
{
    /**
     * Returns the upper-case type name and the sizes written in its parentheses.
     */
    public function shape(ColumnDomain $declared, ?TypeName $written): TypeShape
    {
        $arguments = $written === null ? [] : $written->arguments;
        $name = $written === null || $arguments === []
            ? strtoupper($declared->declared)
            : strtoupper(implode(' ', array_map(static fn (Word $word): string => $word->name->value, $written->words)));

        return TypeShape::fromNumbers($name === '' ? 'BLOB' : $name, array_map(fn (SignedNumber $number): int => $this->number($number), $arguments));
    }

    /**
     * Returns the whole part of the number a type argument is written with, with its sign.
     */
    public function number(SignedNumber $number): int
    {
        $literal = $number->number;
        $value = match (true) {
            $literal instanceof IntegerLiteral => (int) (new NumericLiteral())->decode($literal->digits),
            $literal instanceof HexLiteral => (int) (new NumericLiteral())->decode('0x' . $literal->digits),
            default => (int) (new NumericLiteral())->decode(($literal->whole === '' ? '0' : $literal->whole) . ($literal->fraction === null ? '' : '.' . $literal->fraction) . ($literal->exponent === null ? '' : 'e' . $literal->exponent)),
        };

        return $number->sign === NumberSign::Minus ? -$value : $value;
    }
}
