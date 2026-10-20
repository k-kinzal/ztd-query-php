<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit\Table;

use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Scalar;

/**
 * Establishes a populated relation under the original missing table identity.
 * This fixture does not repair indexes, partitions or expression operands. Any
 * later refusal remains visible and requires its own transition or explanation.
 */
final class History
{
    /**
     * @param array<string, mixed> $before The exact previous native outcome
     * @return list<M\Statement> Explicit setup, all compared before the unchanged retry
     */
    public function steps(M\Statement|Scalar $node, array $before, string $database): array
    {
        $error = $before['error'] ?? null;
        if (!is_array($error) || ($error[0] ?? null) !== 1146 || !is_string($error[2] ?? null)) {
            return [];
        }
        $inputs = new Inputs();
        $name = $inputs->missing($node, $error[2], $database);
        if ($name === null || $name->schema === null) {
            return [];
        }
        $columns = $inputs->columns($node);
        $elements = array_map(static fn ($column): M\Table\Column\ColumnDefinition => new M\Table\Column\ColumnDefinition(new M\Name\ColumnName($column), new M\Table\Column\OrdinaryColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int))), $columns);
        $row = new M\Dml\Insert\InsertedRow(array_map(static fn ($column): M\Literal\NumberLiteral => new M\Literal\NumberLiteral('7'), $columns));
        return [new M\Server\Database\CreateDatabase(true, $name->schema), new M\Table\CreateTable($name, $elements), new M\Dml\Insert\InsertRows(new M\Dml\Insert\InsertInto(false, null, false, new M\Dml\WriteTarget($name)), [$row])];
    }
}
