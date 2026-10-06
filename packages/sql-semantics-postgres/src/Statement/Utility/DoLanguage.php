<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility;

use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Word;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * `LANGUAGE name` in DO: the procedural language the code is written in.
 *
 * Mirrors the `language` element of PostgreSQL's `DoStmt` arguments. The
 * name is a word or a string.
 * Source: https://www.postgresql.org/docs/17/sql-do.html.
 *
 * @visibility public
 * @example Reading the language of an anonymous block
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("DO LANGUAGE plpgsql 'BEGIN END'");
 *     $operation->statement->items[0]->name() // => 'plpgsql'
 */
final class DoLanguage implements Node
{
    use Snapshot;

    /**
     * @param Word|StringConstant $language The language name
     */
    public function __construct(public readonly Word|StringConstant $language)
    {
    }

    /**
     * Answers the language name as the server receives it.
     */
    public function name(): string
    {
        return $this->language instanceof Word ? $this->language->word->value : $this->language->value;
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('LANGUAGE')->node($this->language);
    }
}
