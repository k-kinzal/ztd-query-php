<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Defaults;

use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceGrantDependency;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequencePrivilegeLowering;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\AlterDefaultPrivileges;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\DefaultGrant;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\DefaultObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior;

/**
 * Publishes checked native default sequence privilege statements.
 * @visibility SqlSemantics
 */
final class SequenceDefaultLowering
{
    /**
     * Resolves the policy before publishing the requested operation.
     */
    public function lower(BoundStatement $operation, AlterDefaultPrivileges $statement): GrantSequenceDefaults|RevokeSequenceDefaults
    {
        $context = $operation->context;
        $action = $statement->action;
        Check::input(
            $action->objects === DefaultObjectKind::Sequences && $context->profile->parameters === ParameterStyle::Native
                && $context->relationNames === Comparison::Sensitive,
            'Default sequence privileges require ON SEQUENCES and the native naming profile.'
        );
        [$creators, $schemas] = (new SequenceDefaultScopesLowering())->lower($context, $statement->scopes);
        $lowering = new SequencePrivilegeLowering();
        $policy = new SequenceDefaultPolicy(
            $creators,
            $schemas,
            $lowering->rights($action->privileges),
            $lowering->grantees($context, $action->grantees),
            $context->profile->grammar,
        );
        if ($action instanceof DefaultGrant) {
            return new GrantSequenceDefaults($policy, $action->grantOption);
        }
        $dependency = match ($action->behavior) {
            DropBehavior::Cascade => SequenceGrantDependency::Cascade,
            null, DropBehavior::Restrict => SequenceGrantDependency::Restrict,
        };

        return new RevokeSequenceDefaults($policy, $action->grantOptionOnly, $dependency);
    }
}
