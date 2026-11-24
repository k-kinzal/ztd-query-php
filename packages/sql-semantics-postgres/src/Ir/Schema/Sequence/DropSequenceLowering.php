<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence;

use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Drop;
use SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior;

/**
 * Publishes checked native sequence removal.
 *
 * @visibility SqlSemantics
 */
final class DropSequenceLowering
{
    /**
     * Resolves the complete selector list before constructing the statement.
     */
    public function lower(BoundStatement $operation, Drop $statement): DropSequence
    {
        $context = $operation->context;
        Check::input(
            $context->profile->parameters === ParameterStyle::Native && $context->relationNames === Comparison::Sensitive,
            'DROP SEQUENCE requires the native PostgreSQL namespace profile.'
        );
        Check::input($statement->kind === ObjectKind::Sequence, 'Sequence removal requires DROP SEQUENCE.');
        $resolver = new SequenceTargetLowering();
        $targets = [];
        foreach ($statement->objects as $object) {
            Check::input($object instanceof DottedName, 'Sequence removal requires a relation selector.');
            $name = $object->qualified();
            Check::input($name !== null, 'Sequence removal requires at most three name parts.');
            $targets[] = $resolver->lower($context, $name, $statement->ifExists);
        }

        return new DropSequence($targets, $statement->ifExists, $statement->behavior === DropBehavior::Cascade, $context->profile->grammar);
    }
}
