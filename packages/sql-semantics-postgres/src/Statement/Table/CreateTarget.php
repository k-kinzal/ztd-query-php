<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Writing;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;

/**
 * The new table of CREATE TABLE AS: its name, column names and storage.
 *
 * Mirrors PostgreSQL's `IntoClause` as `create_as_target` builds it (`rel`,
 * `colNames`, `accessMethod`, `options`, `onCommit`, `tableSpaceName`). The
 * column names rename the first columns of the query; WITHOUT OIDS is a no-op
 * and is not kept.
 * Source: https://www.postgresql.org/docs/17/sql-createtableas.html.
 *
 * @visibility public
 * @example Reading the target of CREATE TABLE AS
 *     $create = (new \SqlSemantics\Platform\PostgreSql\Semantics())->analyze('CREATE TABLE t (x) USING heap WITH (fillfactor = 50) TABLESPACE s AS SELECT 1');
 *     [$create->statement->target->columns[0]->value, $create->statement->target->method->value] // => ['x', 'heap']
 */
final class CreateTarget implements Clause
{
    use Snapshot;

    /**
     * @var list<Name> The column names
     */
    public readonly array $columns;

    /**
     * @var list<Definition> The storage parameters
     */
    public readonly array $options;

    /**
     * @param QualifiedName $name The table name
     * @param list<Name> $columns The column names
     * @param Name|null $method The table access method
     * @param list<Definition> $options The storage parameters
     * @param OnCommit|null $onCommit The ON COMMIT action
     * @param Name|null $tablespace The tablespace
     */
    public function __construct(
        public readonly QualifiedName $name,
        array $columns = [],
        public readonly ?Name $method = null,
        array $options = [],
        public readonly ?OnCommit $onCommit = null,
        public readonly ?Name $tablespace = null,
    ) {
        $this->columns = Check::listOf($columns, Name::class, 'Column names are names.');
        $this->options = Check::listOf($options, Definition::class, 'Storage parameters are definitions.');
    }

    /**
     * Derives the storage parameters.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        foreach ($this->options as $option) {
            $option->deriveClause($derivation, $environment);
        }
    }

    /**
     * Writes the target.
     */
    public function render(Output $out): void
    {
        $writing = new Writing();
        (new Spelling())->qualified($out, $this->name);
        if ($this->columns !== []) {
            $writing->parenthesized($out, $this->columns);
        }
        if ($this->method !== null) {
            $out->keyword('USING')->name($this->method);
        }
        if ($this->options !== []) {
            $out->keyword('WITH');
            $writing->definitions($out, $this->options);
        }
        if ($this->onCommit !== null) {
            $out->keyword('ON', 'COMMIT', ...$this->onCommit->keywords());
        }
        if ($this->tablespace !== null) {
            $out->keyword('TABLESPACE')->name($this->tablespace);
        }
    }
}
