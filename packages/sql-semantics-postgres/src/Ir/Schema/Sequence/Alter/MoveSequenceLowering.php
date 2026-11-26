<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequencePresence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceTargetLowering;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Facade\Index\IndexBinding;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\RelationTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Object\SetSchema;

/**
 * Resolves the source and the destination evaluated by native ALTER SEQUENCE.
 *
 * @visibility SqlSemantics
 */
final class MoveSequenceLowering
{
    /**
     * Publishes the complete PostgreSQL sequence move.
     */
    public function lower(BoundStatement $operation, SetSchema $statement): MoveSequence
    {
        $context = $operation->context;
        Check::input(
            $context->profile->parameters === ParameterStyle::Native && $context->relationNames === Comparison::Sensitive,
            'ALTER SEQUENCE SET SCHEMA requires the native naming profile.'
        );
        $reference = $statement->object;
        Check::input(
            $statement->kind === ObjectKind::Sequence && $reference instanceof RelationTarget,
            'SET SCHEMA requires a whole sequence selector.'
        );
        $source = (new SequenceTargetLowering())->lower($context, $reference->relation->name, $statement->ifExists);
        $destination = null;
        if ($source->presence === SequencePresence::Existing) {
            Check::input((new IndexBinding())->known($context, $statement->schema), 'A sequence move requires a declared destination namespace.');
            $destination = new Identifier($statement->schema->value);
        }

        return new MoveSequence($source, $destination, $statement->ifExists, $context->profile->grammar);
    }
}
