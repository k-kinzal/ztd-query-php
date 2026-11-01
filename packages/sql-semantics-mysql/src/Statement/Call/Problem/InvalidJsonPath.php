<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Call\Json\JsonValueCall;
use SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Invalid;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A JSON_VALUE constant path has no selection interpretation.
 *
 * Its position is within the decoded operand, independently of SQL source text.
 *
 * @visibility public
 * @example Keeping a path condition with its owning call
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT JSON_VALUE(NULL, 'bad')")->diagnostics()[0]->message() // => 'Invalid JSON path expression. The error is around character position 1.'
 */
final class InvalidJsonPath implements Diagnostic
{
    use Snapshot;

    /**
     * The byte position within the decoded operand.
     */
    public readonly int $position;

    /**
     * @param JsonValueCall $expression The call containing the invalid constant operand
     */
    public function __construct(public readonly JsonValueCall $expression)
    {
        Check::input($expression->pathValue instanceof Invalid, 'The call contains an invalid constant path.');
        $this->position = $expression->pathValue->position;
    }

    /**
     * Describes the operand boundary reported by MySQL.
     */
    public function message(): string
    {
        return 'Invalid JSON path expression. The error is around character position ' . $this->position . '.';
    }
}
