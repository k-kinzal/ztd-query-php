<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit\Table;

use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Scalar;

/**
 * Supplies the original table-changing request's missing catalog objects.
 * Setup statements preserve referenced identities and populate every new table.
 * The comparison retries the original request without changing any operand.
 */
final class Changes
{
    /**
     * @return list<M\Statement> Catalog changes before the unchanged retry
     */
    public function steps(M\Statement|Scalar $node, int $code): array
    {
        if ($code === 1051 && $node instanceof M\Alter\DropTable) {
            $steps = [];
            foreach ($node->tables as $table) {
                array_push($steps, ...$this->table($table->name, $node->temporary));
            }
            return $steps;
        }
        if ($code === 1091 && $node instanceof M\Alter\DropIndex) {
            $column = new Name('fixture_index_value');
            $definition = new M\Table\Column\ColumnDefinition(new M\Name\ColumnName($column), new M\Table\Column\OrdinaryColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int), [new M\Table\Column\DefaultLiteral(new M\Literal\NumberLiteral('7'))]));
            $primary = strcasecmp($node->index->value, 'PRIMARY') === 0;
            $key = new M\Table\Key\IndexDefinition($primary ? M\Table\Key\IndexKind::Primary : M\Table\Key\IndexKind::Index, [new M\Table\Key\ColumnPart($column)], $primary ? null : new M\Name\ColumnName($node->index));
            return [new M\Alter\AlterTable($node->table, [new M\Alter\Column\AddColumn($definition), new M\Alter\Command\AddConstraint($key)])];
        }
        if ($code === 1017 && $node instanceof M\Alter\RenameTable) {
            return $this->rename($node);
        }
        return [];
    }

    /**
     * @return list<M\Statement> Populated initial sources, excluding names produced by preceding moves
     */
    public function rename(M\Alter\RenameTable $node): array
    {
        $available = [];
        $steps = [];
        foreach ($node->renamings as $rename) {
            $source = serialize([$rename->from->schema?->value, $rename->from->name->value]);
            $target = serialize([$rename->to->schema?->value, $rename->to->name->value]);
            if (!isset($available[$source])) {
                array_push($steps, ...$this->table($rename->from, false));
            }
            unset($available[$source]);
            $available[$target] = true;
            if ($rename->to->schema !== null) {
                $steps[] = new M\Server\Database\CreateDatabase(true, $rename->to->schema);
            }
        }
        return $steps;
    }

    /**
     * @return list<M\Statement> The original table name with an independent populated integer column
     */
    public function table(QualifiedName $name, bool $temporary): array
    {
        $column = new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('fixture_value')), new M\Table\Column\OrdinaryColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int)));
        $steps = $name->schema === null ? [] : [new M\Server\Database\CreateDatabase(true, $name->schema)];
        $steps[] = new M\Table\CreateTable($name, [$column], temporaryWords: $temporary ? 1 : 0, ifNotExists: true);
        $steps[] = new M\Dml\Insert\InsertRows(new M\Dml\Insert\InsertInto(false, null, false, new M\Dml\WriteTarget($name)), [new M\Dml\Insert\InsertedRow([new M\Literal\NumberLiteral('7')])]);
        return $steps;
    }
}
