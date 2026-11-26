<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization;

use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaOwnerLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\Logging\SequenceLoggingModeLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequencePresence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceTargetLowering;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option\OwnerTo;

/**
 * Resolves sequence authorization and combined logging assignments.
 * @visibility SqlSemantics
 */
final class SequenceAuthorizationLowering
{
    /**
     * Resolves action roles for a present source and detaches ignored actions for guarded absence.
     */
    public function lower(BoundStatement $operation, AlterTable $statement): AlterSequenceAuthorization
    {
        $context = $operation->context;
        Check::input(
            $statement->target === AlterTarget::Sequence && $context->profile->parameters === ParameterStyle::Native
                && $context->relationNames === Comparison::Sensitive,
            'Sequence authorization requires ALTER SEQUENCE and the native naming profile.'
        );
        $source = (new SequenceTargetLowering())->lower($context, $statement->relation->name, $statement->ifExists);
        $present = $source->presence === SequencePresence::Existing;
        $actions = [];
        $owners = new SchemaOwnerLowering();
        $logging = new SequenceLoggingModeLowering();
        foreach ($statement->commands as $command) {
            if ($command instanceof OwnerTo) {
                if ($present) {
                    $actions[] = new SequenceAuthorizationAction(new SequencePrincipal($owners->lower($context, $command->owner)));
                }
            } else {
                $actions[] = new SequenceAuthorizationAction($logging->lower($command));
            }
        }

        return new AlterSequenceAuthorization(
            $source,
            $present ? $actions : [],
            $statement->ifExists,
            $context->profile->grammar,
        );
    }
}
