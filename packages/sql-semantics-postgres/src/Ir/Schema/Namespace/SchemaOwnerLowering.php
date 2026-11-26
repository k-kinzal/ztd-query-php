<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpecKind;

/**
 * Binds a nominal or session-supplied PostgreSQL schema owner.
 *
 * @visibility SqlSemantics
 */
final class SchemaOwnerLowering
{
    /**
     * Resolves the role under the selected cluster declarations.
     * @throws InvalidConstruction When the authorization does not select a single owner
     */
    public function lower(AnalysisContext $context, ?RoleSpec $role): Identifier|SchemaSessionRole
    {
        $principal = $role?->name;
        if ($principal !== null) {
            Check::input($context->knowsRole($principal->value), 'A named schema owner requires a declared role.');

            return new Identifier($principal->value);
        }

        return match ($role?->kind) {
            null, RoleSpecKind::CurrentRole, RoleSpecKind::CurrentUser => SchemaSessionRole::Effective,
            RoleSpecKind::SessionUser => SchemaSessionRole::Session,
            RoleSpecKind::Named, RoleSpecKind::Everyone => throw new InvalidConstruction('A schema requires a single owner.'),
        };
    }
}
