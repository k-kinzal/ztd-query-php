<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access;

use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaOwnerLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\SequencePrincipal;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Grant;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Revoke;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpecKind;
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
    public function lower(BoundStatement $operation, Grant|Revoke $statement): GrantSequenceAccess|RevokeSequenceAccess
    {
        $context = $operation->context;
        Check::input(
            $context->profile->parameters === ParameterStyle::Native && $context->relationNames === Comparison::Sensitive,
            'Sequence privileges require the native naming profile.'
        );
        $target = (new SequenceAccessTargetLowering())->lower($context, $statement->target);
        $owners = new SchemaOwnerLowering();
        $grantees = [];
        foreach ($statement->grantees as $role) {
            $grantees[] = new SequenceGrantee($role->kind === RoleSpecKind::Everyone
                ? UniversalGrantee::Everyone : new SequencePrincipal($owners->lower($context, $role)));
        }
        $grantor = $statement->grantor === null ? null : new SequencePrincipal($owners->lower($context, $statement->grantor));
        $rights = [];
        foreach ($statement->privileges as $privilege) {
            $word = match ($privilege->privilege()) {
                'select' => SequenceRight::Select,
                'update' => SequenceRight::Update,
                'usage' => SequenceRight::Usage,
                'rule' => SequenceRight::LegacyRule,
                default => null,
            };
            Check::input($word !== null && $privilege->columns === [], 'A sequence privilege must name one native right without columns.');
            $rights[] = $word;
        }
        $access = new SequenceAccess($target, $rights, $grantees, $grantor, $context->profile->grammar);
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
