<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization;

use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SetSequenceLogging;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SetSequenceLoggingLowering;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option\OwnerTo;

/**
 * Selects the exact checked ALTER SEQUENCE action family.
 * @visibility SqlSemantics
 */
final class SequenceActionsLowering
{
    /**
     * Publishes the authorization family when the action list contains an owner.
     */
    public function lower(BoundStatement $operation, AlterTable $statement): AlterSequenceAuthorization|SetSequenceLogging
    {
        foreach ($statement->commands as $command) {
            if ($command instanceof OwnerTo) {
                return (new SequenceAuthorizationLowering())->lower($operation, $statement);
            }
        }

        return (new SetSequenceLoggingLowering())->lower($operation, $statement);
    }
}
