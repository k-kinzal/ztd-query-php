<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Schema\Definition;

use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Schema\Column;
use SqlSemantics\Statement\Schema\Table;

/**
 * Creates a table from column declarations; no existing schema is altered during analysis.
 * @visibility public
 * @example Declaring a table independently of any database state
 *     $column = new \SqlSemantics\Statement\Schema\Definition\SqliteColumnDefinition(new \SqlSemantics\Statement\Identifier\Name('id'), new \SqlSemantics\Statement\Type\SqliteDeclaration('INTEGER'));
 *     $create = new \SqlSemantics\Statement\Schema\Definition\SqliteCreateTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('users')), columns: $column);
 *     $create->toString() // => 'CREATE TABLE users (id INTEGER)'
 */
final class SqliteCreateTable implements Operation
{
    /**
     * @var non-empty-list<SqliteColumnDefinition>
     */
    public readonly array $columns;

    /**
     * The table declaration supplied to later, independent analysis requests.
     */
    public readonly Table $table;

    /**
     * Keeps one declaration identity for the statement and its users.
     */
    public function __construct(public readonly QualifiedName $name, public readonly bool $temporary = false, public readonly bool $ifNotExists = false, public readonly bool $strict = false, SqliteColumnDefinition ...$columns)
    {
        assert($columns !== [], 'A table definition contains at least one column.');
        assert($name->catalog === null, 'SQLite table creation identifies at most a database and table.');
        foreach ($columns as $column) {
            assert($column->type->strict === $strict, 'Column storage behavior must use the table strictness.');
        }
        $this->columns = array_values($columns);
        $this->table = new Table(new QualifiedName($name->name, $name->schema ?? new Name($temporary ? 'temp' : 'main')), ...array_map(static fn (SqliteColumnDefinition $column): Column => $column->column, $this->columns));
    }

    /**
     * Reconstructs table creation from the declared table and its column definitions.
     */
    public function toString(): string
    {
        return 'CREATE ' . ($this->temporary ? 'TEMP ' : '') . 'TABLE ' . ($this->ifNotExists ? 'IF NOT EXISTS ' : '') . $this->name->toString() . ' (' . implode(', ', array_map(static fn (SqliteColumnDefinition $column): string => $column->toString(), $this->columns)) . ')' . ($this->strict ? ' STRICT' : '');
    }
}
