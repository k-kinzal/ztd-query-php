<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access;

use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaOwnerLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\SequencePrincipal;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Grant;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Revoke;
use SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior;

/**
 * Resolves sequence privilege operands using the selected analysis context.
 * @visibility SqlSemantics
 */
final class SequenceAccessLowering
{
    /**
     * Publishes a complete grant or revoke for the native sequence target.
     */
    public function lower(AnalysisContext $context, Grant|Revoke $statement): GrantSequenceAccess|RevokeSequenceAccess
    {
        Check::input(
            $context->profile->parameters === ParameterStyle::Native && $context->relationNames === Comparison::Sensitive,
            'Sequence privileges require the native naming profile.'
        );
        $target = (new SequenceAccessTargetLowering())->lower($context, $statement->target);
        $owners = new SchemaOwnerLowering();
        $grantor = $statement->grantor === null ? null : new SequencePrincipal($owners->lower($context, $statement->grantor));
        $operands = new SequencePrivilegeLowering();
        $access = new SequenceAccess(
            $target,
            $operands->rights($statement->privileges),
            $operands->grantees($context, $statement->grantees),
            $grantor,
            $context->profile->grammar
        );
        if ($statement instanceof Grant) {
            return new GrantSequenceAccess($access, $statement->grantOption);
        }
        $dependency = match ($statement->behavior) {
            DropBehavior::Cascade => SequenceGrantDependency::Cascade,
            null, DropBehavior::Restrict => SequenceGrantDependency::Restrict,
        };

        return new RevokeSequenceAccess($access, $statement->grantOptionOnly, $dependency);
    }
}
