<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Query;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesQuery;
use SqlSemantics\Platform\Sqlite\Ir\Select;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * Creates and fills a resolved SQLite table from a checked standalone query.
 *
 * @visibility public
 * @example Creating a table from explicit rows
 *     $ir = (new \SqlSemantics\Platform\Sqlite\Semantics())->analyze('CREATE TABLE sample AS VALUES (1), (2)');
 *     $ir->toString() // => 'CREATE TABLE "main"."sample" AS VALUES (1), (2)'
 */
final class CreateTableAs implements SemanticsIR
{
    use Snapshot;

    /**
     * Establishes the source-free and deterministic layout conditions.
     */
    public function __construct(
        public readonly Identifier $schema,
        public readonly Identifier $table,
        public readonly Select|ValuesQuery $query,
        public readonly bool $ifNotExists = false,
    ) {
        Check::input($schema->value !== '', 'A CREATE TABLE AS needs a nonempty schema identifier.');
        Check::input(!str_starts_with($table->key(), 'sqlite_'), 'A CREATE TABLE AS cannot use the reserved SQLite namespace.');
        Check::input(!$query instanceof Select || $query->source === null, 'CREATE TABLE AS requires a source-free SELECT or VALUES.');
        (new CreateAsLayout())->columns($query);
    }

    /**
     * Derives the ordered catalog layout from the checked query.
     * @return non-empty-list<CreatedColumn>
     */
    public function columns(): array
    {
        return (new CreateAsLayout())->columns($this->query);
    }

    /**
     * Identifies the fixed SQLite language profile.
     */
    public function profileId(): string
    {
        return 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters';
    }

    /**
     * Emits the complete declaration and population from the checked operands.
     */
    public function toString(): string
    {
        return 'CREATE TABLE ' . ($this->ifNotExists ? 'IF NOT EXISTS ' : '')
            . $this->schema->quoted() . '.' . $this->table->quoted() . ' AS ' . $this->query->toString();
    }
}
