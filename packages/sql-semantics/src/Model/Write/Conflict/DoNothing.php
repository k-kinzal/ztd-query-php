<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Conflict;

use Override;

/**
 * DoNothing carries no UPDATE assignments or row predicate.
 * @visibility public
 */
final class DoNothing extends \SqlSemantics\Model\Write\ConflictAction
{
    #[Override]
    protected function operation(): ActionKind
    {
        return ActionKind::Nothing;
    }
}
