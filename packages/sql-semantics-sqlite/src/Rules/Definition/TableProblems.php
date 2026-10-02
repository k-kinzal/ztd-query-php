<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Definition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnDefinition;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\DuplicateColumn;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\PrimaryKeyFlaw;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\PrimaryKeyProblem;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\QualifiedTemporaryName;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\StrictTypeViolation;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\UnknownTableOption;
use SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Reports the problems of a table definition that follow from the definition alone.
 *
 * Rule: SQLITE-TABLE-PROBLEMS-001. Diagnostics: a TEMP object qualified with
 * another schema than `temp`; a repeated column name; an unknown table
 * option; in a STRICT table a column without one of the six standard types;
 * more than one primary key; a WITHOUT ROWID table without a primary key;
 * AUTOINCREMENT on a key that is not an integer primary key, or on a WITHOUT
 * ROWID table. Problems SQLite finds only when rows are written, and limits
 * of the library build, are not modeled. Terminates: the column pairs and the
 * options are finite.
 * Source: https://sqlite.org/lang_createtable.html, https://sqlite.org/stricttables.html,
 * https://sqlite.org/withoutrowid.html, https://sqlite.org/autoinc.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class TableProblems
{
    /**
     * Reports every problem of a table definition.
     *
     * @param list<ColumnDomain> $domains The declared type of each column, in column order
     */
    public function report(CreateTable $definition, array $domains, TableKey $key, Derivation $derivation): void
    {
        $this->temporary($definition->name, $definition->temporary, $derivation);
        $this->duplicates($definition->columns, $derivation);
        foreach ($definition->options as $option) {
            if ($option->kind() === null) {
                $derivation->report(new UnknownTableOption($option));
            }
        }
        foreach ($definition->strict() ? $domains : [] as $position => $domain) {
            if (!$domain->standard) {
                $derivation->report(new StrictTypeViolation($definition->columns[$position]->name, $domain->declared));
            }
        }
        $flaws = [
            [$key->constraints > 1, PrimaryKeyFlaw::Repeated],
            [$key->constraints === 0 && $definition->withoutRowid(), PrimaryKeyFlaw::MissingWithoutRowid],
            [$key->autoincrement && $key->rowid === null, PrimaryKeyFlaw::AutoincrementNotIntegerKey],
            [$key->autoincrement && $key->rowid !== null && $definition->withoutRowid(), PrimaryKeyFlaw::AutoincrementWithoutRowid],
        ];
        foreach ($flaws as [$present, $flaw]) {
            if ($present) {
                $derivation->report(new PrimaryKeyProblem($flaw));
            }
        }
    }

    /**
     * Reports a TEMP object whose name is qualified with another schema than `temp`.
     */
    public function temporary(QualifiedName $name, bool $temporary, Derivation $derivation): void
    {
        if ($temporary && $name->schema !== null && !$derivation->context->relationNames->equal($name->schema->value, 'temp')) {
            $derivation->report(new QualifiedTemporaryName($name));
        }
    }

    /**
     * Reports each column whose name an earlier column already has.
     *
     * @param list<ColumnDefinition> $columns
     */
    public function duplicates(array $columns, Derivation $derivation): void
    {
        foreach ($columns as $position => $column) {
            foreach (array_slice($columns, 0, $position) as $earlier) {
                if ($derivation->context->columnNames->equal($earlier->name->value, $column->name->value)) {
                    $derivation->report(new DuplicateColumn($column->name));
                    break;
                }
            }
        }
    }
}
