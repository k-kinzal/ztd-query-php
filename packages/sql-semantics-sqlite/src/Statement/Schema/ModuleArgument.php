<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ModuleArguments;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One argument of a virtual table module: the exact text SQLite hands to the module.
 *
 * Rule: SQLITE-MODULE-ARGUMENT-001. "The module-argument syntax is
 * sufficiently general that the arguments can be ... anything the module
 * wants"; SQLite does not interpret an argument. Arguments are separated by
 * the commas that are outside every parenthesis. The text of an argument
 * runs from its first token to its last token and includes, unchanged, the
 * white space and comments written between them; white space and comments
 * before the first and after the last token are not part of it. An argument
 * without any token is empty: its commas are kept, and SQLite does not pass
 * it to the module. The text is therefore the operand itself and is kept as
 * a string. A text is accepted only when SQLite reads it back as this one
 * argument.
 * Source: https://sqlite.org/lang_createvtab.html, https://sqlite.org/vtab.html#usage
 * (and `sqlite3VtabArgExtend()` in vtab.c of the release). Status: Implemented.
 *
 * @visibility public
 * @example Reading the arguments a module receives
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze("CREATE VIRTUAL TABLE docs USING fts5(title,  body , , tokenize = 'porter ascii')");
 *     [$create->statement->arguments[1]->text, $create->statement->arguments[2]->passed(), $create->statement->arguments[3]->text] // => ['body', false, "tokenize = 'porter ascii'"]
 */
final class ModuleArgument implements Node
{
    use Snapshot;

    /**
     * @param string $text The exact argument text; empty for an argument without tokens
     */
    public function __construct(public readonly string $text)
    {
        Check::input((new ModuleArguments())->single($text), 'A module argument is text SQLite reads back as exactly one argument.');
    }

    /**
     * Tells whether SQLite passes the argument to the module; it skips empty ones.
     */
    public function passed(): bool
    {
        return $this->text !== '';
    }

    /**
     * Writes the text unchanged.
     */
    public function render(Output $out): void
    {
        if ($this->text !== '') {
            $out->spelled($this->text);
        }
    }
}
