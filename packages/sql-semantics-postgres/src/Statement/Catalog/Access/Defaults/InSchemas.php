<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

/**
 * The clause IN SCHEMA of ALTER DEFAULT PRIVILEGES: the defaults apply to objects created in these schemas.
 *
 * Source: https://www.postgresql.org/docs/17/sql-alterdefaultprivileges.html.
 *
 * @visibility public
 * @example Reading the schemas
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DEFAULT PRIVILEGES IN SCHEMA app, audit GRANT SELECT ON TABLES TO joe');
 *     $operation->scopes[0]->schemas[1]->value // => 'audit'
 */
final class InSchemas implements DefaultScope
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var non-empty-list<Name> The schemas in the order written
     */
    public readonly array $schemas;

    /**
     * @param list<Name> $schemas The schemas in the order written, at least one
     */
    public function __construct(array $schemas, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
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
