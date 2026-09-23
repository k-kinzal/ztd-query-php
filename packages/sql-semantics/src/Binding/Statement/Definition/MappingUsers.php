<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Definition\Foreign\MappingPrincipal;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Separates named PostgreSQL roles from session-principal and public mapping selectors.
 * @visibility SqlSemantics
 */
final class MappingUsers
{
    /**
     * Resolves lexical role identities without substituting runtime usernames.
     * @throws InvalidSql
     */
    public static function read(Node $source): NamedRole|MappingPrincipal
    {
        $token = $source->tokens()[0];
        $principal = match ($token->name) {
            'USER', 'CURRENT_USER' => MappingPrincipal::CurrentUser,
            'CURRENT_ROLE' => MappingPrincipal::CurrentRole,
            'SESSION_USER' => MappingPrincipal::SessionUser,
            default => null,
        };
        if ($principal !== null) {
            return $principal;
        }
        $name = (new Identifiers(Dialect::PostgreSql))->name($token);
        if ($name === '' || $name === 'none') {
            throw new InvalidSql(InputViolation::MappingRole, $source);
        }
        return $name === 'public' ? MappingPrincipal::PublicDefault : new NamedRole($name);
    }
}
