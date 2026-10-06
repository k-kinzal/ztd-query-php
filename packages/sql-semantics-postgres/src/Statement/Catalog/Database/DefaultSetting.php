<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Option\OptionArgument;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;

/**
 * The keyword DEFAULT written as the value of a database option: the option takes its default.
 *
 * Source: https://www.postgresql.org/docs/17/sql-createdatabase.html.
 *
 * @visibility public
 * @example Reading the value of an option set to its default
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE DATABASE d TEMPLATE DEFAULT');
 *     $operation->statement->options[0]->value // => \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\DefaultSetting::Default
 */
enum DefaultSetting: string implements OptionArgument
{
    case Default = 'DEFAULT';

    /**
     * Derives nothing: a keyword holds no expression.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the keyword.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->value);
    }
}
