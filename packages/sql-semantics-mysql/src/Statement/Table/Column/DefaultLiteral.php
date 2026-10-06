<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A column default written as a literal, NULL or the current timestamp function, without parentheses.
 *
 * A literal default is the value itself; CURRENT_TIMESTAMP (and its synonyms) is the only function MySQL accepts without parentheses, for TIMESTAMP and DATETIME columns.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/data-type-defaults.html.
 *
 * @visibility public
 * @example Reading the clause of a column
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a TIMESTAMP DEFAULT 1)');
 *     $create->statement->elements[0]->specification->attributes[0] instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultLiteral // => true
 */
final class DefaultLiteral implements ColumnAttribute
{
    use Snapshot;

    /**
     * @param Scalar $value The value
     */
    public function __construct(public readonly Scalar $value)
    {
    }

    /**
     * Derives the value inside the table definition.
     */
    public function deriveAttribute(Derivation $derivation, Environment $scope): void
    {
        $derivation->scalar($this->value, $scope);
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('DEFAULT')->node($this->value);
    }
}
