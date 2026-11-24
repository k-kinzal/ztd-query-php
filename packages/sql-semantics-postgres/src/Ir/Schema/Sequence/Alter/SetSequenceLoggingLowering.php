<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter;

use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequencePresence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceTargetLowering;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\TableAction;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\TableActionKind;

/**
 * Resolves a sequence and lowers its native logged or unlogged actions.
 * @visibility SqlSemantics
 */
final class SetSequenceLoggingLowering
{
    /**
     * Checks the sequence form and prepares the ordered action set.
     *
     * @throws InvalidConstruction When an action is outside the logging form
     */
    public function lower(BoundStatement $operation, AlterTable $statement): SetSequenceLogging
    {
        $context = $operation->context;
        Check::input(
            $statement->target === AlterTarget::Sequence && $context->profile->parameters === ParameterStyle::Native
                && $context->relationNames === Comparison::Sensitive,
            'Sequence logging requires ALTER SEQUENCE and the native naming profile.'
        );
        $source = (new SequenceTargetLowering())->lower($context, $statement->relation->name, $statement->ifExists);
        $modes = [];
        foreach ($statement->commands as $command) {
            Check::input($command instanceof TableAction, 'Sequence logging requires SET LOGGED or SET UNLOGGED.');
            $modes[] = match ($command->kind) {
                TableActionKind::SetLogged => SequenceLogging::Logged,
                TableActionKind::SetUnlogged => SequenceLogging::Unlogged,
                TableActionKind::SetWithoutOids,
                TableActionKind::SetWithoutCluster,
                TableActionKind::NotOf,
                TableActionKind::EnableRowSecurity,
                TableActionKind::DisableRowSecurity,
                TableActionKind::ForceRowSecurity,
                TableActionKind::NoForceRowSecurity,
                TableActionKind::EnableAllTriggers,
                TableActionKind::EnableUserTriggers,
                TableActionKind::DisableAllTriggers,
                TableActionKind::DisableUserTriggers => throw new InvalidConstruction('Sequence logging requires SET LOGGED or SET UNLOGGED.'),
            };
        }

        return new SetSequenceLogging(
            $source,
            $source->presence === SequencePresence::Existing ? $modes : [],
            $statement->ifExists,
            $context->profile->grammar,
        );
    }
}
