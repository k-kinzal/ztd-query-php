<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * The SCHEMA option of CREATE EXTENSION: the schema the objects of the extension go into.
 *
 * Source: https://www.postgresql.org/docs/17/sql-createextension.html.
 *
 * @visibility public
 * @example Reading the target schema
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension\ExtensionSchema(new \SqlSemantics\Statement\Identifier\Name('ext')))->schema->value // => 'ext'
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
