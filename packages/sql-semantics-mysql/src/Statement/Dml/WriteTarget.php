<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Query\TableShapes;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteMisuse;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteRule;
use SqlSemantics\Platform\MySql\Statement\Name\AliasMark;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\NamedRelation;
use SqlSemantics\Statement\Reference\Table\CommonTable;
use SqlSemantics\Statement\Snapshot;

/**
 * The one table INSERT, REPLACE, LOAD DATA or a single-table DELETE writes, with its correlation name and partition selection.
 *
 * Rule: MYSQL-DML-TARGET-001. The name resolves like a table reference
 * (MYSQL-TABLE-SHAPES-001); a common table is no table a statement can
 * write, which the server reports as a non-updatable target. Partition names
 * are not checked: the context holds no partitions. Terminates: no child
 * relation. Source: https://dev.mysql.com/doc/refman/8.4/en/insert.html,
 * https://dev.mysql.com/doc/refman/8.4/en/delete.html,
 * https://dev.mysql.com/doc/refman/8.4/en/partitioning-selection.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the table a DELETE writes
 *     $delete = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('DELETE FROM shop.t AS x PARTITION (p0) WHERE x.a = 1');
 *     [$delete->statement->table->name()->schema?->value, $delete->statement->table->alias()?->value, count($delete->statement->table->partitions)] // => ['shop', 'x', 1]
 */
final class WriteTarget implements NamedRelation
{
    use Snapshot;

    /**
     * @var list<Name> The selected partitions in written order; empty for every partition
     */
    public readonly array $partitions;

    /**
     * @param QualifiedName $name The table name with its optional database
     * @param Name|null $alias The correlation name; only a single-table DELETE of MySQL 8.0 and later writes one
     * @param list<Name> $partitions The selected partitions
     * @param AliasMark $mark What is written before the alias
     */
    public function __construct(public readonly QualifiedName $name, public readonly ?Name $alias = null, array $partitions = [], public readonly AliasMark $mark = AliasMark::As)
    {
        Check::input($alias !== null || $mark === AliasMark::As, 'A table without alias has no alias mark.');
        Check::input($name->catalog === null, 'A table is qualified by at most a database.');
        $this->partitions = Check::listOf($partitions, Name::class, 'A partition selection names partitions.');
    }

    /**
     * Answers the table name.
     */
    public function name(): QualifiedName
    {
        return $this->name;
    }

    /**
     * Answers the correlation name.
     */
    public function alias(): ?Name
    {
        return $this->alias;
    }

    /**
     * Resolves the name and derives the row shape of the table.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        $fact = (new TableShapes())->named($this->name, $derivation, $environment);
        if ($fact->table instanceof CommonTable) {
            $derivation->report(new WriteMisuse(WriteRule::CommonTableTarget));
        }

        return $fact;
    }

    /**
     * Writes the name, the correlation name and the partitions.
     */
    public function render(Output $out): void
    {
        if ($this->name->schema !== null) {
            $out->name($this->name->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->name->name, NameUse::Relation);
        if ($this->alias !== null) {
            $this->mark->write($out);
            $out->name($this->alias, NameUse::Alias);
        }
        if ($this->partitions !== []) {
            $out->keyword('PARTITION')->symbol('(');
            foreach ($this->partitions as $position => $partition) {
                if ($position > 0) {
                    $out->symbol(',');
                }
                $out->name($partition, NameUse::Alias);
            }
            $out->symbol(')');
        }
    }
}
