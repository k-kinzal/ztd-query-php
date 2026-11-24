<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceTargetLowering;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\RelationTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Rename;

/**
 * Binds the native sequence selector and fixes its destination name.
 *
 * @visibility SqlSemantics
 */
final class RenameSequenceLowering
{
    /**
     * Publishes a fully resolved sequence rename.
     */
    public function lower(BoundStatement $operation, Rename $statement): RenameSequence
    {
        $context = $operation->context;
        Check::input(
            $context->profile->parameters === ParameterStyle::Native && $context->relationNames === Comparison::Sensitive,
            'ALTER SEQUENCE RENAME requires the native naming profile.'
        );
        $reference = $statement->object;
        Check::input(
            $statement->kind === ObjectKind::Sequence && $reference instanceof RelationTarget && $statement->member === null,
            'Sequence rename requires a whole sequence selector.'
        );
        $source = (new SequenceTargetLowering())->lower($context, $reference->relation->name, $statement->ifExists);

        return new RenameSequence($source, new Identifier($statement->newName->value), $statement->ifExists, $context->profile->grammar);
    }
}
