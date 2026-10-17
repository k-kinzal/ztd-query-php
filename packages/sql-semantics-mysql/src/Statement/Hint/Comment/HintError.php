<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Hint\Comment;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Snapshot;

/**
 * The problem a hint comment stops at: why, and where in the statement text.
 *
 * The server reports the problem as warning 1064, "<failure> near '<text>'
 * at line <n>", where the text is the statement from the offset on, cut to
 * 80 bytes, or only the end of the comment when the problem is that the
 * comment ends, and the line is the line of the offset (verified on a live
 * 8.4 server). Source:
 * https://dev.mysql.com/doc/refman/8.4/en/optimizer-hints.html#optimizer-hints-syntax.
 *
 * @visibility public
 * @example Writing the warning of a problem
 *     (new \SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintError(\SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintFailure::Syntax, 11))->message('SELECT /*+ FOO *' . '/ 1') // => "Optimizer hint syntax error near 'FOO *" . "/ 1' at line 1"
 */
final class HintError
{
    use Snapshot;

    /**
     * @param HintFailure $failure Why the comment is not read further
     * @param int $offset The byte offset in the statement text the server reports the problem at
     * @param bool $closing Whether the problem is that the comment ends there
     */
    public function __construct(public readonly HintFailure $failure, public readonly int $offset, public readonly bool $closing = false)
    {
        Check::input($offset >= 0, 'An offset is not negative.');
    }

    /**
     * Answers the text of the warning the server raises, for the statement text the offset is in.
     */
    public function message(string $statement): string
    {
        $line = substr_count(substr($statement, 0, $this->offset), "\n") + 1;

        $near = $this->closing ? '*/' : mb_strcut(substr($statement, $this->offset), 0, 80, 'UTF-8');

        return $this->failure->value . " near '" . $near . "' at line " . $line;
    }
}
