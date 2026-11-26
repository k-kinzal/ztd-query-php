<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension;

use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * The SCHEMA option of CREATE EXTENSION: the schema the objects of the extension go into.
 *
 * Source: https://www.postgresql.org/docs/17/sql-createextension.html.
 *
 * @visibility SqlSemantics
 * @example Reading the target schema
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension\ExtensionSchema(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('ext')))->schema->value // => 'ext'
 */
final class ExtensionSchema implements ExtensionOption
{
    use Snapshot;

    /**
     * @param Name $schema The schema name
     */
    public function __construct(public readonly Name $schema)
    {
    }

    /**
     * Answers the option name the server receives.
     */
    public function option(): string
    {
        return 'schema';
    }

    /**
     * Writes SCHEMA and the name.
     */
    public function render(Output $out): void
    {
        $out->keyword('SCHEMA')->name($this->schema, NameUse::Column);
    }
}
