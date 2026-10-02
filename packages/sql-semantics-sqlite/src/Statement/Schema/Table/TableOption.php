<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Table;

use SqlSemantics\Platform\Sqlite\Statement\Lexical\Word;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One option written after the closing parenthesis of a table definition.
 *
 * Rule: SQLITE-TABLE-OPTION-001. The grammar accepts `WITHOUT name` and
 * `name` for any name. SQLite compares the written text of the name, without
 * regard to ASCII case, with `rowid` after WITHOUT and with `strict`
 * otherwise; every other word, and either word in quotes, is the error
 * "unknown table option". The word is therefore kept with its quoting.
 * Source: https://sqlite.org/syntax/table-options.html. Status: Implemented.
 *
 * @visibility public
 * @example Telling a known option from an unknown one
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE t (a PRIMARY KEY) STRICT, WITHOUT "rowid"');
 *     [$create->statement->options[0]->kind(), $create->statement->options[1]->kind()] // => [\SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableOptionKind::Strict, null]
 */
final class TableOption implements Node
{
    use Snapshot;

    /**
     * @param Word $word The option word with its quoting
     * @param bool $without Whether WITHOUT is written before the word
     */
    public function __construct(public readonly Word $word, public readonly bool $without = false)
    {
    }

    /**
     * Answers the option SQLite reads, or null when it rejects the word.
     */
    public function kind(): ?TableOptionKind
    {
        if ($this->without) {
            return $this->word->is('ROWID') ? TableOptionKind::WithoutRowid : null;
        }

        return $this->word->is('STRICT') ? TableOptionKind::Strict : null;
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        if ($this->without) {
            $out->keyword('WITHOUT');
        }
        $out->node($this->word);
    }
}
