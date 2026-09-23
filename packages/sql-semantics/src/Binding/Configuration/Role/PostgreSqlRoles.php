<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Configuration\Role;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Reads a concrete role identity or a session role lookup without evaluating it.
 * @visibility SqlSemantics
 */
final class PostgreSqlRoles
{
    /**
     * Keeps quoted role names distinct from session-role keywords.
     * @throws InvalidSql
     */
    public static function read(Node $source): NamedRole|SessionRole
    {
        $token = $source->tokens()[0];
        $role = SessionRole::tryFrom($token->name);
        if ($role !== null) {
            return $role;
        }
        $name = (new Identifiers(Dialect::PostgreSql))->name($token);
        if (in_array($name, ['', 'public', 'none'], true)) {
            throw new InvalidSql(InputViolation::OwnershipRole, $source);
        }
        return new NamedRole($name);
    }
}
