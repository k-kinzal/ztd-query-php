<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\ForeignOptions;
use SqlSemantics\Platform\PostgreSql\Statement\Option\GenericOption;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to create foreign tables for the tables of a remote schema.
 *
 * Rule: PG-IMPORT-SCHEMA-001. Mirrors `ImportForeignSchemaStmt`: remote
 * schema, restriction, server, local schema and options. The created
 * foreign tables take their columns from the remote server, so the statement
 * provides no declaration; the restricted table names are remote names and
 * are not resolved. Source: https://www.postgresql.org/docs/17/sql-importforeignschema.html. Status: Implemented.
 *
 * @visibility public
 * @example Importing a remote schema
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('IMPORT FOREIGN SCHEMA remote LIMIT TO (a, b) FROM SERVER s INTO local');
 *     [$operation->statement->localSchema->value, count($operation->statement->restriction?->tables ?? []), $operation->declarations()] // => ['local', 2, []]
 */
final class ImportForeignSchema implements Statement
{
    use Snapshot;

    /**
     * @var list<GenericOption> The options
     */
    public readonly array $options;

    /**
     * @param Name $remoteSchema The schema on the server
     * @param ImportRestriction|null $restriction The LIMIT TO or EXCEPT clause, when written
     * @param Name $server The foreign server
     * @param Name $localSchema The local schema the foreign tables go into
     * @param list<GenericOption> $options The options
     */
    public function __construct(
        public readonly Name $remoteSchema,
        public readonly ?ImportRestriction $restriction,
        public readonly Name $server,
        public readonly Name $localSchema,
        array $options = [],
    ) {
        $this->options = Check::listOf($options, GenericOption::class, 'Import options are a list of options.');
    }

    /**
     * Derives nothing: the tables are remote and the created columns come from the server.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('IMPORT', 'FOREIGN', 'SCHEMA')->name($this->remoteSchema, NameUse::Column)->node($this->restriction)
            ->keyword('FROM', 'SERVER')->name($this->server, NameUse::Column)->keyword('INTO')->name($this->localSchema, NameUse::Column);
        (new ForeignOptions())->write($out, $this->options);
    }
}
