<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension;

use SqlSemantics\Statement\Node;

/**
 * One option of CREATE EXTENSION: SCHEMA, VERSION, FROM or CASCADE.
 *
 * Source: https://www.postgresql.org/docs/17/sql-createextension.html.
 *
 * @visibility public
 * @example Reading the option name the server receives
 *     \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension\ExtensionCascade::Cascade->option() // => 'cascade'
 */
interface ExtensionOption extends Node
{
    /**
     * Answers the option name the server receives.
     */
    public function option(): string;
}
