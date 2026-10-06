<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Problem\UtilityProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Problem\UtilityProblemKind;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `DO [LANGUAGE name] code`: a request to run an anonymous code block.
 *
 * Rule: PG-DO-001. Mirrors PostgreSQL's `DoStmt`, whose arguments are the
 * code string and the language in the order written. The code is text of
 * the procedural language, which the language handler reads when the command
 * runs; it is opaque to SQL and kept as the decoded string. Diagnostics: a
 * block without code, and code or a language written twice.
 * Source: https://www.postgresql.org/docs/17/sql-do.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the code of an anonymous block
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('DO $$BEGIN NULL; END$$ LANGUAGE plpgsql');
 *     [$operation->statement->code()?->value, $operation->statement->language()?->name()] // => ['BEGIN NULL; END', 'plpgsql']
 * @example Refusing a block without items
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Utility\DoBlock([]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class DoBlock implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<StringConstant|DoLanguage> The code strings and language clauses in the order written
     */
    public readonly array $items;

    /**
     * @param array<array-key, object> $items The code strings and language clauses in the order written; at least one
     *
     * @throws InvalidConstruction When an item is neither a string nor a language clause, or there is none
     */
    public function __construct(array $items)
    {
        $list = [];
        foreach ($items as $item) {
            if (!$item instanceof StringConstant && !$item instanceof DoLanguage) {
                throw new InvalidConstruction('The items of DO are code strings and language clauses.');
            }
            $list[] = $item;
        }
        Check::input(array_is_list($items) && $list !== [], 'DO takes at least one item.');
        $this->items = $list;
    }

    /**
     * Answers the code when exactly one code string is written.
     */
    public function code(): ?StringConstant
    {
        $codes = [];
        foreach ($this->items as $item) {
            if ($item instanceof StringConstant) {
                $codes[] = $item;
            }
        }

        return count($codes) === 1 ? $codes[0] : null;
    }

    /**
     * Answers the language clause when exactly one is written; without one the server uses `plpgsql`.
     */
    public function language(): ?DoLanguage
    {
        $languages = [];
        foreach ($this->items as $item) {
            if ($item instanceof DoLanguage) {
                $languages[] = $item;
            }
        }

        return count($languages) === 1 ? $languages[0] : null;
    }

    /**
     * Reports a block without code and an item written twice.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $codes = 0;
        $languages = 0;
        foreach ($this->items as $item) {
            $codes += $item instanceof StringConstant ? 1 : 0;
            $languages += $item instanceof DoLanguage ? 1 : 0;
        }
        if ($codes > 1 || $languages > 1) {
            $derivation->report(new UtilityProblem(UtilityProblemKind::RedundantOptions));
        } elseif ($codes === 0) {
            $derivation->report(new UtilityProblem(UtilityProblemKind::NoInlineCode));
        }
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('DO');
        foreach ($this->items as $item) {
            $out->node($item);
        }
    }
}
