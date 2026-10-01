<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic\Statement;

use SqlSemantics\Semantic\Expression\Binary;
use SqlSemantics\Semantic\Expression\Coalesce;
use SqlSemantics\Semantic\Expression\ColumnReference;
use SqlSemantics\Semantic\Expression\Literal;
use SqlSemantics\Semantic\Expression\NullIf;
use SqlSemantics\Semantic\Expression\Operands;
use SqlSemantics\Semantic\Expression\Parameter;
use SqlSemantics\Semantic\Expression\Unary;
use SqlSemantics\Semantic\Scope;

/**
 * One ordered row of insertion values.
 * @example Reading semantic relationships
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('INSERT INTO bar (foo) VALUES (1)');
 *     $statement->rows[0]->toString() // => '(1)'
 *
 * @visibility public
 */
final class ValuesRow
{
    /**
     * @var list<ColumnReference|Literal|Parameter|Binary|Unary|Coalesce|NullIf>
     */
    public readonly array $values;

    /**
     * Constructs the value and asserts the relationships required by its fields.
     */
    public function __construct(public readonly Scope $scope, ColumnReference|Literal|Parameter|Binary|Unary|Coalesce|NullIf ...$values)
    {
        assert($scope->tables === [], 'VALUES expressions do not read the insertion target.');
        foreach ($values as $value) {
            Operands::check($scope, $value);
        }
        $this->values = array_values($values);
    }

    /**
     * Reconstructs SQL from the semantic values without consulting source syntax.
     */
    public function toString(): string
    {
        return '(' . implode(', ', array_map(static fn ($value): string => $value->toString(), $this->values)) . ')';
    }
}
