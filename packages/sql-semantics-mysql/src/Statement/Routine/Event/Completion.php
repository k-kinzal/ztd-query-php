<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Event;

/**
 * What happens to an event after its last run: ON COMPLETION PRESERVE keeps it, ON COMPLETION NOT PRESERVE drops it.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-event.html.
 *
 * @visibility public
 * @example Listing the cases
 *     count(\SqlSemantics\Platform\MySql\Statement\Routine\Event\Completion::cases()) // => 2
 */
enum Completion
{
    case Preserve;
    case NotPreserve;
}
