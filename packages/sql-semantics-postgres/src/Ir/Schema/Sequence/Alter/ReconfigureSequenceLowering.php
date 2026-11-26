<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter;

use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequencePresence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceTargetLowering;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\AlterSequence;

/**
 * Publishes the checked reconfiguration for the selected native sequence.
 * @visibility SqlSemantics
 */
final class ReconfigureSequenceLowering
{
    /**
     * Resolves the source before interpreting any change operands.
     */
    public function lower(BoundStatement $operation, AlterSequence $statement): ReconfigureSequence
    {
        $context = $operation->context;
        Check::input(
            $context->profile->parameters === ParameterStyle::Native && $context->relationNames === Comparison::Sensitive,
            'ALTER SEQUENCE requires the native naming profile.'
        );
        $source = (new SequenceTargetLowering())->lower($context, $statement->name, $statement->ifExists);
        $changes = $source->presence === SequencePresence::Existing
            ? (new SequenceChangesLowering())->lower($context, $statement->options)
            : null;

        return new ReconfigureSequence($source, $changes, $statement->ifExists, $context->profile->grammar);
    }
}
