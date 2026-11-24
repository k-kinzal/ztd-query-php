<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Schema\CreateSchema as ParsedCreateSchema;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpecKind;

/**
 * Resolves the ownership expression before publishing a schema declaration.
 *
 * @visibility SqlSemantics
 */
final class CreateSchemaLowering
{
    /**
     * Publishes the standalone schema declaration.
     */
    public function lower(BoundStatement $operation, ParsedCreateSchema $statement): CreateSchema
    {
        $context = $operation->context;
        Check::input(
            $context->profile->parameters === ParameterStyle::Native && $context->relationNames === Comparison::Sensitive,
            'CREATE SCHEMA requires the native PostgreSQL namespace profile.'
        );
        Check::input($statement->elements === [], 'CREATE SCHEMA elements require a checked namespace composition.');
        $role = $statement->authorization;
        $owner = match ($role?->kind) {
            null, RoleSpecKind::CurrentRole, RoleSpecKind::CurrentUser => SchemaSessionRole::Effective,
            RoleSpecKind::SessionUser => SchemaSessionRole::Session,
            RoleSpecKind::Named => $this->bindOwner($context, $role->name),
            RoleSpecKind::Everyone => throw new InvalidConstruction('A schema requires a single owner.'),
        };
        $name = $statement->name === null ? null : new Identifier($statement->name->value);

        return new CreateSchema($name, $owner, $statement->ifNotExists, $context->profile->grammar);
    }

    private function bindOwner(AnalysisContext $context, ?Name $name): Identifier
    {
        Check::input($name !== null && $context->knowsRole($name->value), 'A named schema owner requires a declared role.');

        return new Identifier($name->value);
    }
}
