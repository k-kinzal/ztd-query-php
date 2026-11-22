<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults;

use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * The clause IN SCHEMA of ALTER DEFAULT PRIVILEGES: the defaults apply to objects created in these schemas.
 *
 * Source: https://www.postgresql.org/docs/17/sql-alterdefaultprivileges.html.
 *
 * @visibility SqlSemantics
 */
final class InSchemas implements DefaultScope
{
    use Snapshot;

    /**
     * @var non-empty-list<Name> The schemas in the order written
     */
    public readonly array $schemas;

    /**
     * @param list<Name> $schemas The schemas in the order written, at least one
     */
    public function __construct(array $schemas)
    {
        $this->schemas = Check::listOf($schemas, Name::class, 'IN SCHEMA names at least one schema.', 1);
    }

    /**
     * Answers the option the clause fills.
     */
    public function option(): string
    {
        return 'schemas';
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('IN', 'SCHEMA');
        foreach ($this->schemas as $position => $schema) {
            if ($position > 0) {
                $out->symbol(',');
            }
            $out->name($schema, NameUse::Column);
        }
    }
}
