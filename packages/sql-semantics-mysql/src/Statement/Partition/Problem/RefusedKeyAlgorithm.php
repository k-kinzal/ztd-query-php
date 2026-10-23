<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Partition\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Partition\KeyAlgorithms;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A KEY partition algorithm outside its two-value domain.
 *
 * @visibility public
 * @example Retaining the refused algorithm without source SQL
 *     (new \SqlSemantics\Platform\MySql\Statement\Partition\Problem\RefusedKeyAlgorithm(new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('3'), 10))->message() // => 'A KEY partition algorithm is one or two.'
 */
final class RefusedKeyAlgorithm implements Diagnostic
{
    use Snapshot;

    /**
     * @param Numeral $number The refused algorithm operand
     * @param int $offset Its input position, used only for syntax diagnostics
     */
    public function __construct(public readonly Numeral $number, public readonly int $offset)
    {
        Check::input(!(new KeyAlgorithms())->accepts($number), 'A key algorithm refusal retains an invalid algorithm.');
        Check::input($offset >= 0, 'A key algorithm refusal has a non-negative input position.');
    }

    /**
     * Describes the missing algorithm interpretation.
     */
    public function message(): string
    {
        return 'A KEY partition algorithm is one or two.';
    }
}
