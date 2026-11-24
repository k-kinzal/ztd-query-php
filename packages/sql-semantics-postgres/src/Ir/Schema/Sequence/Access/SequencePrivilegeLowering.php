<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access;

use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaOwnerLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\SequencePrincipal;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Privilege;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpecKind;

/**
 * Binds the privilege and recipient lists shared by native sequence ACL changes.
 * @visibility SqlSemantics
 */
final class SequencePrivilegeLowering
{
    /**
     * Resolves nominal grantees and preserves the native universal and session roles.
     * @param list<RoleSpec> $roles
     * @return list<SequenceGrantee>
     */
    public function grantees(AnalysisContext $context, array $roles): array
    {
        $owners = new SchemaOwnerLowering();
        $grantees = [];
        foreach ($roles as $role) {
            $grantees[] = new SequenceGrantee($role->kind === RoleSpecKind::Everyone
                ? UniversalGrantee::Everyone : new SequencePrincipal($owners->lower($context, $role)));
        }

        return $grantees;
    }

    /**
     * Checks that each requested privilege has a defined sequence effect.
     * @param list<Privilege> $privileges
     * @return list<SequenceRight>
     */
    public function rights(array $privileges): array
    {
        $rights = [];
        foreach ($privileges as $privilege) {
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

        return $rights;
    }
}
