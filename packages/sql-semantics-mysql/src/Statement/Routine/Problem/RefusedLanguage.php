<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A routine LANGUAGE other than SQL and JAVASCRIPT, which the grammar refuses while parsing.
 *
 * Source: https://dev.mysql.com/doc/refman/9.1/en/create-procedure.html (verified on 8.4.7 and 9.1.0).
 *
 * @visibility public
 * @example Reading the refused language
 *     (new \SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedLanguage(new \SqlSemantics\Statement\Identifier\Name('python'), 30))->message() // => "Unknown routine language 'python'"
 */
final class RefusedLanguage implements Diagnostic
{
    use Snapshot;

    /**
     * @param Name $language The language as written
     * @param int $offset The input offset where the language name starts
     */
    public function __construct(public readonly Name $language, public readonly int $offset)
    {
        Check::input($offset >= 0, 'An input location is nonnegative.');
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return sprintf("Unknown routine language '%s'", $this->language->value);
    }
}
