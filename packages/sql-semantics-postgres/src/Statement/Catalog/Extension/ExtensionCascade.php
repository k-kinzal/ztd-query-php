<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension;

use SqlSemantics\Rendering\Output;

/**
 * The CASCADE option of CREATE EXTENSION: extensions the new one depends on are installed too.
 *
 * Source: https://www.postgresql.org/docs/17/sql-createextension.html.
 *
 * @visibility public
 * @example Spelling the option
 *     \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension\ExtensionCascade::Cascade->value // => 'CASCADE'
 */
enum ExtensionCascade: string implements ExtensionOption
{
    case Cascade = 'CASCADE';

    /**
     * Answers the option name the server receives.
     */
    public function option(): string
    {
        return 'cascade';
    }

    /**
     * Writes the keyword.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->value);
    }
}
