<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Column;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A column default written as an expression in parentheses (MySQL 8.0.13 and later).
 *
 * The expression is evaluated when a row is inserted; it may refer to earlier columns of the table, and the server stores it as an expression default even when it is a literal.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/data-type-defaults.html#data-type-defaults-explicit.
 *
 * @visibility SqlSemantics
 */
final class DefaultExpression implements ColumnAttribute
{
    use Snapshot;

    /**
     * @param Scalar $expression The default expression
     */
    public function __construct(public readonly Scalar $expression)
    {
    }

    /**
     * Derives the expression inside the table definition.
     */
    public function deriveAttribute(Derivation $derivation, Environment $scope): void
    {
        $derivation->scalar($this->expression, $scope);
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('DEFAULT')->symbol('(')->node($this->expression)->symbol(')');
    }
}
