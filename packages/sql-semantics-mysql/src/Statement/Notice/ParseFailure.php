<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Notice;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\Warning;
use SqlSemantics\Statement\Snapshot;

/**
 * The ordered place of a problem found while reading or contextualizing a statement.
 *
 * Rule: MYSQL-PARSE-FAILURE-001. The server looks up the collation of
 * COLLATE and refuses CAST ... AT LOCAL when it parses the construct: it
 * records the error and parses on, so the warnings of the constructs it
 * parses after it follow the error, and every such error of the statement
 * is recorded. A cast to an array of a type no multi-valued index takes,
 * and a cast to TIME or DATETIME with a precision above 6, stop the parse
 * in 8.4 and 9.1: no construct after it raises a warning. In 8.0 those
 * cast errors are recorded and parsing continues. Role-list input errors
 * continue even beyond the enclosing stored-program statement; the
 * endsProgramStatement flag distinguishes that continuation. The
 * problem is reported as a diagnostic too; this notice keeps its order
 * among the warnings. Terminates: no recursion. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/show-warnings.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the place of an unknown collation
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT BINARY ('a' COLLATE nope)");
 *     [$query->facts->warnings[0]->message(), $query->facts->warnings[1]->message()] // => ["Unknown collation: 'nope'", "'BINARY expr' is deprecated and will be removed in a future release. Please use CAST instead"]
 */
final class ParseFailure implements Warning
{
    use Snapshot;

    /**
     * @param Diagnostic $problem The problem the server finds while it parses
     * @param bool $aborts Whether the server stops parsing at the problem, so the constructs it would parse later raise no warning
     * @param NoticePhase $phase Whether grammar interpretation or subsequent contextualization raises the problem
     * @param bool $endsProgramStatement Whether a stored-program statement stops parsing after this continuing error
     */
    public function __construct(public readonly Diagnostic $problem, public readonly bool $aborts = false, public readonly NoticePhase $phase = NoticePhase::Syntax, public readonly bool $endsProgramStatement = true)
    {
        \SqlSemantics\Diagnostic\Check::input($phase !== NoticePhase::Resolution, 'A parse failure belongs to grammar interpretation or contextualization, not name resolution.');
    }

    /**
     * Answers the text of the problem.
     */
    public function message(): string
    {
        return $this->problem->message();
    }
}
