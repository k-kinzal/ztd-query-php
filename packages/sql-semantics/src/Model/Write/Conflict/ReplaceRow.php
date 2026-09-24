<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Conflict;

use Override;

/**
 * ReplaceRow carries no UPDATE assignments or row predicate.
 * @visibility public
 * @example Deriving the replace operation
 *     $action = new \SqlSemantics\Model\Write\Conflict\ReplaceRow(new \SqlSemantics\Model\Write\Conflict\AnyConflict(), new \SqlParser\Parser\Node('conflict', 0, []));
 *     $action->action // => \SqlSemantics\Model\Write\Conflict\ActionKind::Replace
 */
final class ReplaceRow extends \SqlSemantics\Model\Write\ConflictAction
{
    #[Override]
    protected function operation(): ActionKind
    {
        return ActionKind::Replace;
    }
}
