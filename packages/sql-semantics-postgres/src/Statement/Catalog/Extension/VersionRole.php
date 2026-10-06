<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension;

/**
 * Whether a version written in CREATE EXTENSION is the version to install or the old version to update from.
 *
 * Source: https://www.postgresql.org/docs/17/sql-createextension.html.
 *
 * @visibility public
 * @example Spelling the role of an installed version
 *     \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension\VersionRole::Target->value // => 'VERSION'
 */
enum VersionRole: string
{
    case Target = 'VERSION';
    case Source = 'FROM';
}
