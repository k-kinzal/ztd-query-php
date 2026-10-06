<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Insert;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountedList;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountMismatch;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Nullability;

/**
 * The alias `AS name [(column, ...)]` of the new row of INSERT, which ON DUPLICATE KEY UPDATE refers to (MySQL 8.0.19 and later).
 *
 * Rule: MYSQL-DML-ROW-ALIAS-001. The alias is a relation with one column
 * per written column, in order, holding the value that row would insert;
 * its columns take the names of the alias column list, or the names of the
 * written columns. The statement derives the alias at a position whose
 * environment holds the written columns as its fields, or, when the
 * written table is not declared completely, that table as its relation: the
 * columns then depend on the missing declaration. A column list of
 * another length, or a repeated column name, is reported as the server
 * does for derived column names. Terminates: no child. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/insert-on-duplicate.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the alias of the new row
 *     $insert = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('INSERT INTO t (a, b) VALUES (1, 2) AS new (m, n) ON DUPLICATE KEY UPDATE a = m + n');
 *     [$insert->statement->alias->name->value, count($insert->statement->alias->columns)] // => ['new', 2]
 */
final class RowAlias implements Relation
{
    use Snapshot;

    /**
     * @var list<Name> The column names in written order; empty for the names of the written columns
     */
    public readonly array $columns;

    /**
     * @param Name $name The alias
     * @param list<Name> $columns The column names
     */
    public function __construct(public readonly Name $name, array $columns = [])
    {
        $this->columns = Check::listOf($columns, Name::class, 'The columns of a row alias are names.');
    }

    /**
     * Derives one column per written column, named by the column list when it is written.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        $missing = [];
        foreach ($environment->relations as $relation) {
            array_push($missing, ...$relation->shape->missing);
        }
        if ($missing !== []) {
            $slots = [];
            foreach ($this->columns as $column) {
                $slots[] = new OutputSlot($column, new Dependent($missing), Nullability::Dependent);
            }

            return new RelationFact(new RowShape($slots, $this->columns === [] ? $missing : []));
        }
        $written = $environment->aliases;
        if ($this->columns !== [] && count($this->columns) !== count($written)) {
            $derivation->report(new CountMismatch(CountedList::DerivedColumns, count($written), count($this->columns)));
        }
        $slots = [];
        $seen = [];
        foreach ($written as $position => $field) {
            $name = $this->columns[$position] ?? $field->name;
            $key = $name === null ? '' : $derivation->context->columnNames->fold($name->value);
            if (isset($seen[$key])) {
                $derivation->report(new Misuse(MisuseRule::DuplicateColumn));
            }
            $seen[$key] = true;
            $slots[] = new OutputSlot($name, $field->type, $field->nullability, null, $field->slot);
        }

        return new RelationFact(new RowShape($slots));
    }

    /**
     * Writes the alias and its column list.
     */
    public function render(Output $out): void
    {
        $out->keyword('AS')->name($this->name, NameUse::Alias);
        if ($this->columns !== []) {
            $out->symbol('(');
            foreach ($this->columns as $position => $column) {
                if ($position > 0) {
                    $out->symbol(',');
                }
                $out->name($column, NameUse::Alias);
            }
            $out->symbol(')');
        }
    }
}
