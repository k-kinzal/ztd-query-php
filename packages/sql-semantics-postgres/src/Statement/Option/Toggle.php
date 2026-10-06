<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Option;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;

/**
 * The keywords TRUE, FALSE and ON written as an option or parameter value.
 *
 * The grammar reads these three reserved words where it otherwise reads a
 * word or a string, and passes them on as the texts `true`, `false` and `on`.
 * Source: https://www.postgresql.org/docs/17/sql-set.html.
 *
 * @visibility public
 * @example Reading the text a keyword value stands for
 *     \SqlSemantics\Platform\PostgreSql\Statement\Option\Toggle::On->text() // => 'on'
 */
enum Toggle: string implements OptionArgument
{
    case True = 'TRUE';
    case False = 'FALSE';
    case On = 'ON';

    /**
     * Answers the text the server receives for the keyword.
     */
    public function text(): string
    {
        return strtolower($this->value);
    }

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
