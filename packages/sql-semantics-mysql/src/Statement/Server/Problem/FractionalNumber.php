<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Problem;

use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A decimal or floating number where the server accepts only an integer.
 *
 * The grammar reads it through dec_num_error, whose action rejects the
 * statement with ER_ONLY_INTEGERS_ALLOWED.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/number-literals.html.
 *
 * @visibility public
 * @example Describing the problem
 *     (new \SqlSemantics\Platform\MySql\Statement\Server\Problem\FractionalNumber(new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('1.5')))->message() // => 'Only integers are allowed, not 1.5 (ER_ONLY_INTEGERS_ALLOWED).'
 */
final class FractionalNumber implements Diagnostic
{
    use Snapshot;

    /**
     * @param Numeral $number The number as written
     */
    public function __construct(public readonly Numeral $number)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'Only integers are allowed, not ' . $this->number->text . ' (ER_ONLY_INTEGERS_ALLOWED).';
    }
}
