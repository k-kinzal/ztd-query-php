<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Type;

use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\HexLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\RealLiteral;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * A numeric literal with its optional written sign, as used for type arguments and pragma values.
 *
 * Source: https://sqlite.org/syntax/signed-number.html.
 *
 * @visibility public
 * @example Reading a type argument
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT CAST(1 AS DECIMAL(+10, 2))');
 *     $argument = $query->statement->columns[0]->expression->target->arguments[0];
 *     [$argument->sign, $argument->number->digits] // => [\SqlSemantics\Platform\Sqlite\Statement\Type\NumberSign::Plus, '10']
 */
final class SignedNumber implements Node
{
    use Snapshot;

    /**
     * @param NumberSign|null $sign The written sign
     * @param IntegerLiteral|HexLiteral|RealLiteral $number The numeric literal
     */
    public function __construct(public readonly ?NumberSign $sign, public readonly IntegerLiteral|HexLiteral|RealLiteral $number)
    {
    }

    /**
     * Writes the sign directly before the number.
     */
    public function render(Output $out): void
    {
        if ($this->sign !== null) {
            $out->symbol($this->sign->value)->glue();
        }
        $out->node($this->number);
    }
}
