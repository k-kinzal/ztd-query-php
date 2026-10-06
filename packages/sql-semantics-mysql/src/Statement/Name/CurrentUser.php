<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Name;

use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * The account of the session, written `CURRENT_USER` or `CURRENT_USER()` at an account position.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-functions.html#function_current-user.
 *
 * @visibility public
 * @example Rendering the current user
 *     $out = new \SqlSemantics\Rendering\Output(new \SqlSemantics\Platform\MySql\Rendering\Codec(\SqlSemantics\Contract\GrammarRelease::MySql847));
 *     (new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser())->render($out);
 *     $out->pieces()[0]->text // => 'CURRENT_USER'
 */
final class CurrentUser implements Account
{
    use Snapshot;

    /**
     * @param OptionalWords $parentheses Whether the optional empty parentheses are written
     */
    public function __construct(public readonly OptionalWords $parentheses = OptionalWords::Omitted)
    {
    }

    /**
     * Writes the keyword and the parentheses when they are written.
     */
    public function render(Output $out): void
    {
        $out->keyword('CURRENT_USER');
        if ($this->parentheses === OptionalWords::Written) {
            $out->glue()->symbol('(')->symbol(')');
        }
    }
}
