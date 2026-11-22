<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Column;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ConstraintScope;
use SqlSemantics\Platform\Sqlite\Statement\Type\NumberSign;

/**
 * A DEFAULT column constraint whose value is a literal, optionally signed.
 *
 * Rule: SQLITE-COLUMN-DEFAULT-LITERAL-001. The literal is NULL, a number, a
 * string, a BLOB or one of CURRENT_TIME, CURRENT_DATE and CURRENT_TIMESTAMP.
 * A minus sign negates the literal; a plus sign is read and changes nothing.
 * The grammar accepts a sign before every literal, a string included. The
 * CURRENT_ keywords are evaluated when a row is inserted.
 * Source: https://sqlite.org/lang_createtable.html#the_default_clause.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class DefaultLiteral implements ColumnConstraint
{
    use Snapshot;

    /**
     * @param Scalar $literal The literal; one of the literal expression classes
     * @param NumberSign|null $sign The sign written before the literal
     */
    public function __construct(public readonly Scalar $literal, public readonly ?NumberSign $sign = null)
    {
        Check::input(str_starts_with($literal::class, 'SqlSemantics\\Platform\\Sqlite\\Statement\\Expression\\Literal\\'), 'An unparenthesized default is a literal; any other expression is written in parentheses.');
    }

    /**
     * Derives the literal; it sees no column.
     */
    public function deriveConstraint(Derivation $derivation, ConstraintScope $scope): void
    {
        $derivation->scalar($this->literal, $scope->constant);
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('DEFAULT');
        if ($this->sign !== null) {
            $out->symbol($this->sign->value);
        }
        $out->node($this->literal);
    }
}
