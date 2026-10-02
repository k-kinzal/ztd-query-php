<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Schema\Definition;

use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Schema\Column;
use SqlSemantics\Statement\Schema\DeclarationProvider;
use SqlSemantics\Statement\Schema\SqliteRowIdentifier;
use SqlSemantics\Statement\Schema\Table;

/**
 * Creates a table from column declarations; no existing schema is altered during analysis.
 * @visibility public
 * @example Declaring a table independently of any database state
 *     $column = new \SqlSemantics\Statement\Schema\Definition\SqliteColumnDefinition(new \SqlSemantics\Statement\Identifier\Name('id'), new \SqlSemantics\Statement\Type\SqliteDeclaration('INTEGER'));
 *     $create = new \SqlSemantics\Statement\Schema\Definition\SqliteCreateTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('users')), columns: $column);
 *     $create->toString() // => 'CREATE TABLE users (id INTEGER)'
 */
final class SqliteCreateTable implements DeclarationProvider
{
    use \SqlSemantics\Statement\Validation\Snapshot;

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
    public function __construct(public readonly QualifiedName $name, public readonly bool $temporary = false, public readonly bool $ifNotExists = false, public readonly bool $strict = false, public readonly \SqlSemantics\Statement\Contract\LanguageProfile $profile = new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), SqliteColumnDefinition ...$columns)
    {
        \SqlSemantics\Statement\Validation\Check::input($profile->grammar->database() === 'sqlite', 'A SQLite declaration requires a SQLite profile.');
        \SqlSemantics\Statement\Validation\Check::input($columns !== [], 'A table definition contains at least one column.');
        \SqlSemantics\Statement\Validation\Check::input($name->catalog === null, 'SQLite table creation identifies at most a database and table.');
        $alias = null;
        foreach ($columns as $column) {
            \SqlSemantics\Statement\Validation\Check::input($column->type->strict === $strict, 'Column storage behavior must use the table strictness.');
            foreach ($column->constraints as $constraint) {
                if ($alias === null && $column->type->permitsRowidAlias() && $constraint instanceof ColumnPrimaryKey && $constraint->direction !== KeyDirection::Descending) {
                    $alias = $column->column;
                }
            }
        }
        $this->columns = array_values($columns);
        $this->table = new Table(new QualifiedName($name->name, $name->schema ?? new Name($temporary ? 'temp' : 'main')), $profile, ...[...array_map(static fn (SqliteColumnDefinition $column): Column => $column->column, $this->columns), new SqliteRowIdentifier($alias)]);
    }

    /**
     * Exposes this CREATE's original declaration regardless of conditional existence options.
     * @return list<Table>
     */
    public function declaredTables(): array
    {
        return [$this->table];
    }

    /**
     * Reconstructs table creation from the declared table and its column definitions.
     */
    public function toString(): string
    {
        return 'CREATE ' . ($this->temporary ? 'TEMP ' : '') . 'TABLE ' . ($this->ifNotExists ? 'IF NOT EXISTS ' : '') . $this->name->toString() . ' (' . implode(', ', array_map(static fn (SqliteColumnDefinition $column): string => $column->toString(), $this->columns)) . ')' . ($this->strict ? ' STRICT' : '');
    }
}
