<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit;

use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Scalar;

/**
 * Opens a populated table under the original handler identity.
 *
 * A repeated read keeps its predicate, key operands and limit unchanged. Missing
 * columns and other subsequent conditions stay visible in the comparison report.
 */
final class HandlerHistory
{
    /**
     * @return list<M\Statement> Explicit catalog, row and cursor setup
     */
    public function steps(M\Statement|Scalar $node, int $code): array
    {
        if ($code !== 1109 || (!$node instanceof M\Dml\Handler\HandlerClose && !$node instanceof M\Dml\Handler\HandlerScan && !$node instanceof M\Dml\Handler\HandlerIndexRead && !$node instanceof M\Dml\Handler\HandlerIndexSeek)) {
            return [];
        }
        $alias = $node instanceof M\Dml\Handler\HandlerClose ? $node->handler : $node->handler->name;
        $index = $node instanceof M\Dml\Handler\HandlerIndexRead || $node instanceof M\Dml\Handler\HandlerIndexSeek ? $node->index : null;
        $width = $node instanceof M\Dml\Handler\HandlerIndexSeek ? count($node->values) : 1;
        $schema = new Name('structure_handler_fixture');
        $table = new QualifiedName(new Name('fixture_rows'), $schema);
        $columns = array_map(static fn (int $position): Name => new Name($position === 1 ? 'a' : 'key' . $position), range(1, $width));
        $elements = array_map(static fn (Name $name): M\Table\Column\ColumnDefinition => new M\Table\Column\ColumnDefinition(new M\Name\ColumnName($name), new M\Table\Column\OrdinaryColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int), [new M\Table\Column\DefaultLiteral(new M\Literal\NumberLiteral('0'))])), $columns);
        $parts = array_map(static fn (Name $name): M\Table\Key\ColumnPart => new M\Table\Key\ColumnPart($name), $columns);
        $elements[] = new M\Table\Key\IndexDefinition(M\Table\Key\IndexKind::Primary, $parts);
        if ($index !== null && strcasecmp($index->value, 'PRIMARY') !== 0) {
            $elements[] = new M\Table\Key\IndexDefinition(M\Table\Key\IndexKind::Index, array_map(static fn (Name $name): M\Table\Key\ColumnPart => new M\Table\Key\ColumnPart($name), $columns), new M\Name\ColumnName($index));
        }
        $rows = array_map(static fn (int $value): M\Dml\Insert\InsertedRow => new M\Dml\Insert\InsertedRow(array_map(static fn (Name $column): M\Literal\NumberLiteral => new M\Literal\NumberLiteral((string) $value), $columns)), [0, 1, 2, 3]);
        return [new M\Server\Database\CreateDatabase(true, $schema), new M\Table\CreateTable($table, $elements), new M\Dml\Insert\InsertRows(new M\Dml\Insert\InsertInto(false, null, false, new M\Dml\WriteTarget($table)), $rows), new M\Dml\Handler\HandlerOpen($table, $alias)];
    }
}
