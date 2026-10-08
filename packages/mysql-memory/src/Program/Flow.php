<?php

declare(strict_types=1);

namespace MySqlMemory\Program;

/**
 * Where control goes from a statement of a stored program instead of to the next one.
 *
 * LEAVE ends the labeled block or loop, ITERATE starts the labeled loop again, RETURN ends the
 * stored function with a value, and an EXIT handler ends the block that declares it once it
 * has run. After a CONTINUE handler of an error in a step of a statement, such as the condition
 * of IF, control resumes after that statement.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/flow-control-statements.html,
 * https://dev.mysql.com/doc/refman/8.4/en/declare-handler.html.
 *
 * @visibility MySqlMemory
 */
enum Flow
{
    case Leave;
    case Iterate;
    case Return;
    case Exit;
    case Resume;
}
