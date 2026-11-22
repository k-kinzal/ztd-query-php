<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Statement\Option\OptionArgument;

/**
 * The keyword DEFAULT written as the value of a database option: the option takes its default.
 *
 * Source: https://www.postgresql.org/docs/17/sql-createdatabase.html.
 *
 * @visibility SqlSemantics
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
