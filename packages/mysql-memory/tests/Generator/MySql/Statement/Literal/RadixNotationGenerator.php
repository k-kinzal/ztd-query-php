<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Literal;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RadixNotation values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\RadixNotation>
 */
final class RadixNotationGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Literal\RadixNotation> */
    use Composition;

    /**
     * Selects an enum alternative through Eris.
     */
    public function __construct()
    {
        $this->delegate = Generators::elements(\SqlSemantics\Platform\MySql\Statement\Literal\RadixNotation::cases());
    }
    /**
     * Selects the alternatives valid in a particular SQL role.
     *
     * @param non-empty-list<\SqlSemantics\Platform\MySql\Statement\Literal\RadixNotation> $cases The alternatives allowed by the parent structure
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\RadixNotation>
     */
    public static function selecting(array $cases): ErisGenerator
    {
        return Generators::elements($cases);
    }

    /**
     * Generates only notations accepted by the already-generated digits.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\RadixNotation>
     */
    public static function forDigits(\SqlSemantics\Platform\MySql\Statement\Literal\Radix $radix, string $digits): ErisGenerator
    {
        $notation = \SqlSemantics\Platform\MySql\Statement\Literal\RadixNotation::class;
        if ($digits === '') {
            return self::selecting([$notation::Quoted, $notation::UpperQuoted]);
        }
        if ($radix === \SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal && strlen($digits) % 2 !== 0) {
            return self::selecting([$notation::Prefixed]);
        }
        return new self();
    }
}
