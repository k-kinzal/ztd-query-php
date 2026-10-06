<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program;

/**
 * What happens after a handler ran: CONTINUE with the next statement or EXIT the block that declares the handler.
 *
 * Each case holds its keyword.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/declare-handler.html.
 *
 * @visibility public
 * @example Reading the keyword of an action
 *     \SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerAction::Exit->value // => 'EXIT'
 */
enum HandlerAction: string
{
    case Continue = 'CONTINUE';
    case Exit = 'EXIT';
}
