<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Definition;

use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\ImplicitColumn;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Derives the declaration a table definition with a column list provides.
 *
 * Rule: SQLITE-TABLE-DECLARATION-001. The declaration has one column per
 * column definition, in order, with the declared type SQLite records
 * (SQLITE-TYPE-RECORDING-001). A TEMP table belongs to the `temp` schema.
 *
 * NULL facts: a column is never NULL when it has a NOT NULL constraint, when
 * it is the integer primary key (reading it yields the rowid), or when it is
 * a primary key column of a WITHOUT ROWID or STRICT table. "Unless the column
 * is an INTEGER PRIMARY KEY or the table is a WITHOUT ROWID table or a STRICT
 * table or the column is declared NOT NULL, SQLite allows NULL values in a
 * PRIMARY KEY column": every other column, generated columns included, can be
 * NULL.
 *
 * Row identifier: a table that is not WITHOUT ROWID has a rowid, found by the
 * names `rowid`, `oid` and `_rowid_` unless a declared column has that name,
 * and not part of `*`. When the table has an integer primary key
 * (SQLITE-PRIMARY-KEY-001), those names denote that declared column;
 * otherwise they denote an undeclared INTEGER column that is never NULL. A
 * WITHOUT ROWID table has no such column.
 *
 * Generated columns are ordinary columns for reading, VIRTUAL and STORED
 * alike. An ordinary table has no hidden columns; those exist only in
 * virtual tables, whose columns the module decides.
 * Terminates: one pass over the columns.
 * Source: https://sqlite.org/lang_createtable.html, https://sqlite.org/lang_createtable.html#rowid,
 * https://sqlite.org/withoutrowid.html, https://sqlite.org/stricttables.html, https://sqlite.org/gencol.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class TableDeclaration
{
    /**
     * Answers the declared type of each column, in column order.
     *
     * @return list<ColumnDomain>
     */
    public function domains(CreateTable $definition): array
    {
        $domains = [];
        foreach ($definition->columns as $column) {
            $domains[] = (new TypeRecording())->domain($column->type, $definition->strict());
        }

        return $domains;
    }

    /**
     * Answers the name a definition declares its object under: a TEMP object belongs to the `temp` schema.
     */
    public function name(QualifiedName $written, bool $temporary): QualifiedName
    {
        return $temporary ? new QualifiedName($written->name, new Name('temp')) : $written;
    }

    /**
     * Builds the declaration.
     *
     * @param list<ColumnDomain> $domains The declared type of each column, in column order
     */
    public function table(CreateTable $definition, array $domains, TableKey $key, LanguageProfile $profile): Table
    {
        $rowid = $definition->withoutRowid() ? null : $key->rowid;
        $keyed = $definition->withoutRowid() || $definition->strict();
        $columns = [];
        foreach ($definition->columns as $position => $column) {
            $notNull = $column->notNull() || $position === $rowid || ($keyed && in_array($position, $key->columns, true));
            $columns[] = new Column($column->name, $domains[$position], $notNull ? Nullability::NotNull : Nullability::Nullable);
        }

        return new Table($this->name($definition->name, $definition->temporary), $profile, $columns, $definition->withoutRowid() ? [] : [$this->rowid($rowid === null ? null : $columns[$rowid])]);
    }

    /**
     * Answers the implicit row identifier: an alias of the integer primary key column, or an undeclared INTEGER column.
     */
    public function rowid(?Column $alias): ImplicitColumn
    {
        return new ImplicitColumn(
            [new Name('rowid'), new Name('oid'), new Name('_rowid_')],
            $alias ?? new Column(new Name('rowid'), new ColumnDomain('INTEGER'), Nullability::NotNull),
        );
    }
}
