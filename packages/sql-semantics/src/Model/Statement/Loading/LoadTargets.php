<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Loading;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\ReplicationRelease;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Reference\ColumnReference;
use SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference;
use SqlSemantics\Model\Scalar\Reference\UnresolvedVariableReference;
use SqlSemantics\Model\Scalar\Reference\VariableReference;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Write\Assignment;
use SqlSemantics\Model\Write\Assignment\DefaultAssignment;
use SqlSemantics\Model\Write\Assignment\ScalarAssignment;
use SqlSemantics\Schema\VariableScope;

/**
 * Validates the operands MySQL loads share: the file name, the receiving columns or user variables and the SET assignments.
 * @visibility SqlSemantics
 */
final class LoadTargets
{
    /**
     * Requires MySQL, a text file name and, once the release is known, a release that spells the source.
     * @throws InvalidStructure
     */
    public static function source(Origin $origin, LoadSource $source, Literal $file): void
    {
        ReplicationRelease::require($origin, 'LOAD DATA and LOAD XML');
        if ($source === LoadSource::S3) {
            ReplicationRelease::require($origin, 'Loading from S3', 80200);
        }
        if ($file->type->dialect !== Dialect::MySql || $file->literalKind !== LiteralKind::Text) {
            throw new InvalidStructure('A load requires a MySQL text literal naming its input.');
        }
    }

    /**
     * Requires each input field to go to a column or a user variable.
     * @param list<Expression> $targets
     * @throws InvalidStructure
     */
    public static function fields(array $targets): void
    {
        Collections::objects($targets, Expression::class);
        foreach ($targets as $target) {
            $scope = match (true) {
                $target instanceof VariableReference => $target->definition->scope,
                $target instanceof UnresolvedVariableReference => $target->scope,
                $target instanceof ColumnReference, $target instanceof UnresolvedColumnReference => VariableScope::User,
                default => null,
            };
            if ($scope !== VariableScope::User) {
                throw new InvalidStructure('A load field goes to a column or a user variable.');
            }
        }
    }

    /**
     * Requires each SET item to assign one column a value or its default.
     * @param list<Assignment> $assignments
     * @throws InvalidStructure
     */
    public static function assignments(array $assignments): void
    {
        Collections::objects($assignments, Assignment::class);
        foreach ($assignments as $assignment) {
            if (!$assignment instanceof ScalarAssignment && !$assignment instanceof DefaultAssignment) {
                throw new InvalidStructure('A load assigns one column at a time.');
            }
        }
    }
}
