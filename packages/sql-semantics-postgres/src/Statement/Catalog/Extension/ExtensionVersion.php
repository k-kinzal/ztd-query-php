<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension;

use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Word;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * The VERSION or FROM option of CREATE EXTENSION.
 *
 * A version is written as a word or as a string; the server receives its
 * text either way. Source: https://www.postgresql.org/docs/17/sql-createextension.html.
 *
 * @visibility public
 * @example Reading the version to install
 *     $version = new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension\ExtensionVersion(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension\VersionRole::Target, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant('1.2'));
 *     $version->option() // => 'new_version'
 */
final class ExtensionVersion implements ExtensionOption
{
    use Snapshot;

    /**
     * @param VersionRole $role Whether this is the version to install or the old version
     * @param Word|StringConstant $version The version
     */
    public function __construct(public readonly VersionRole $role, public readonly Word|StringConstant $version)
    {
    }

    /**
     * Answers the option name the server receives.
     */
    public function option(): string
    {
        return match ($this->role) {
            VersionRole::Target => 'new_version',
            VersionRole::Source => 'old_version',
        };
    }

    /**
     * Writes the keyword and the version.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->role->value)->node($this->version);
    }
}
