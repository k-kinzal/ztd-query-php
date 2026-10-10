<?php

declare(strict_types=1);

namespace MySqlMemory\Dictionary;

use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Query;

/**
 * A view: its query, the columns it declares, and the attributes SHOW CREATE VIEW writes.
 *
 * Views share the namespace of the tables of their database. The query is kept resolved, with
 * the tables it read when it was resolved; a query whose tables have changed since is resolved
 * again before it is read, in the database that was current when the view was created.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-view.html.
 *
 * @visibility MySqlMemory
 */
final class View
{
    /**
     * The statement the view was created with, which SHOW CREATE VIEW writes the query of.
     */
    public readonly Operation $created;

    /**
     * The query as the view was created with it.
     */
    public readonly Query $definition;

    /**
     * @var list<int> The position in the output of the query of each column of the view, when the query was resolved again and returns them in another order; empty for their own order
     */
    public array $positions = [];

    /**
     * The index-level hints the query of the view keeps, as SHOW CREATE VIEW writes them after its first SELECT, or the empty string.
     */
    public string $hints = '';

    /**
     * @param string $schema The database of the view
     * @param string $name The name of the view
     * @param array{string, string} $definer The user and host of the definer
     * @param string $algorithm UNDEFINED, MERGE or TEMPTABLE
     * @param string $security DEFINER or INVOKER
     * @param string $check The check option: empty for none, CASCADED or LOCAL
     * @param string $select The text of the query as it was written
     * @param string $database The database that was current when the view was created, where the unqualified names of the query are looked up
     * @param Operation $operation The resolved statement the query belongs to
     * @param Query $query The resolved query
     * @param Table $declaration The declaration statements are bound against
     * @param array<string, Table> $tables The declarations of the tables and views the query read when it was resolved, by `database.name`
     * @param array{string, string} $charsets The character_set_client and collation_connection the view was created with
     */
    public function __construct(
        public readonly string $schema,
        public readonly string $name,
        public readonly array $definer,
        public readonly string $algorithm,
        public readonly string $security,
        public readonly string $check,
        public readonly string $select,
        public readonly string $database,
        public Operation $operation,
        public Query $query,
        public Table $declaration,
        public array $tables,
        public readonly array $charsets,
    ) {
        $this->created = $operation;
        $this->definition = $query;
    }

    /**
     * Answers the statement SHOW CREATE VIEW writes for the view, around the text of its query.
     *
     * @param string $name The name of the view as the statement writes it: qualified when it is in another database than the current one
     * @param string $text The query as the server writes it
     */
    public function create(string $name, string $text): string
    {
        return 'CREATE ALGORITHM=' . $this->algorithm . ' DEFINER=' . Routine::quoted($this->definer[0]) . '@' . Routine::quoted($this->definer[1]) . ' SQL SECURITY ' . $this->security . ' VIEW ' . $name . ' AS ' . $text . ($this->check === '' ? '' : ' WITH ' . $this->check . ' CHECK OPTION');
    }
}
