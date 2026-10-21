<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit\Table;

use Fuzz\Structure\Context\Columns;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Scalar;

/**
 * Supplies the indexed text relation required by an unchanged MATCH expression.
 * The preceding ordinary relation witness remains in the comparison history.
 */
final class FullTextHistory
{
    /**
     * @return list<M\Statement> Compared catalog changes before retrying the same expression
     */
    public function steps(M\Statement|Scalar $node, int $code): array
    {
        if ($code !== 1191 || !$node instanceof Scalar) {
            return [];
        }
        $bindings = (new Columns())->bindings($node);
        $searches = $this->searches($node);
        if (count($bindings) !== 1 || $searches === []) {
            return [];
        }
        $binding = $bindings[0];
        $name = $binding['relation']->name;
        $elements = array_map(static fn (Name $column): M\Table\Column\ColumnDefinition => new M\Table\Column\ColumnDefinition(new M\Name\ColumnName($column), new M\Table\Column\OrdinaryColumn(new M\Type\Character(M\Type\Kind\CharacterKind::Text))), $binding['columns']);
        foreach ($searches as $index => $search) {
            $names = array_map(static fn (M\Name\ColumnUse $column): string => mb_strtolower($column->name->value), $search->columns);
            if (count(array_unique($names)) !== count($names)) {
                return [];
            }
            $parts = array_map(static fn (M\Name\ColumnUse $column): M\Table\Key\ColumnPart => new M\Table\Key\ColumnPart($column->name), $search->columns);
            $elements[] = new M\Table\Key\IndexDefinition(M\Table\Key\IndexKind::FullText, $parts, new M\Name\ColumnName(new Name('fixture_fulltext_' . $index)));
        }
        $row = new M\Dml\Insert\InsertedRow(array_map(static fn (Name $column): M\Literal\StringLiteral => new M\Literal\StringLiteral(['populated fixture document']), $binding['columns']));
        return [new M\Alter\DropTable(false, false, [new M\Alter\TargetTable($name)]), new M\Table\CreateTable($name, $elements), new M\Dml\Insert\InsertRows(new M\Dml\Insert\InsertInto(false, null, false, new M\Dml\WriteTarget($name)), [$row])];
    }

    /**
     * Finds MATCH operands in the ambient scalar namespace, stopping at subqueries.
     *
     * @return list<M\Expression\Access\FullTextSearch>
     */
    public function searches(Scalar $node): array
    {
        $found = [];
        $pending = [$node];
        while ($pending !== []) {
            $value = array_pop($pending);
            if ($value instanceof Query) {
                continue;
            }
            if ($value instanceof M\Expression\Access\FullTextSearch) {
                $found[] = $value;
            }
            if (is_array($value)) {
                array_push($pending, ...array_values($value));
            } elseif ($value instanceof Node) {
                array_push($pending, ...array_values(get_object_vars($value)));
            }
        }
        return $found;
    }
}
