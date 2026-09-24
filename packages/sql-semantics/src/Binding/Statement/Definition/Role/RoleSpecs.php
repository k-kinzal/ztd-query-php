<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Role;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\PublicRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Reads role specifications in each domain PostgreSQL defines: names, references, and grantees.
 * @visibility SqlSemantics
 */
final class RoleSpecs
{
    /**
     * A definition, deletion, or rename requires a concrete role name.
     * @throws InvalidSql
     */
    public static function name(Node $source, Identifiers $identifiers): NamedRole
    {
        $role = self::grantee($source, $identifiers);
        if (!$role instanceof NamedRole) {
            throw new InvalidSql(InputViolation::RoleName, $source);
        }
        return $role;
    }

    /**
     * A role reference accepts session roles but neither public nor none.
     * @throws InvalidSql
     */
    public static function reference(Node $source, Identifiers $identifiers): NamedRole|SessionRole
    {
        $role = self::grantee($source, $identifiers);
        if ($role instanceof PublicRole) {
            throw new InvalidSql(InputViolation::RoleReference, $source);
        }
        return $role;
    }

    /**
     * A grantee additionally accepts PUBLIC; the GROUP noise word is dropped.
     * @throws InvalidSql
     */
    public static function grantee(Node $source, Identifiers $identifiers): NamedRole|SessionRole|PublicRole
    {
        $spec = Tree::outer($source, ['RoleSpec'])[0] ?? $source;
        $token = $spec->tokens()[0];
        $session = SessionRole::tryFrom($token->name);
        if ($session !== null) {
            return $session;
        }
        $name = $identifiers->name($token);
        if ($name === 'public') {
            return PublicRole::Public;
        }
        if ($name === '' || $name === 'none') {
            throw new InvalidSql(InputViolation::RoleReference, $source);
        }
        return new NamedRole($name);
    }

    /**
     * @return non-empty-list<NamedRole|SessionRole>
     * @throws InvalidSql
     */
    public static function references(Node $list, Identifiers $identifiers): array
    {
        return Collections::nonEmpty(array_map(static fn (Node $spec): NamedRole|SessionRole => self::reference($spec, $identifiers), Tree::outer($list, ['RoleSpec'])));
    }

    /**
     * @return non-empty-list<NamedRole>
     * @throws InvalidSql
     */
    public static function names(Node $list, Identifiers $identifiers): array
    {
        return Collections::nonEmpty(array_map(static fn (Node $spec): NamedRole => self::name($spec, $identifiers), Tree::outer($list, ['RoleSpec'])));
    }

    /**
     * @return non-empty-list<NamedRole|SessionRole|PublicRole>
     * @throws InvalidSql
     */
    public static function grantees(Node $list, Identifiers $identifiers): array
    {
        return Collections::nonEmpty(array_map(static fn (Node $grantee): NamedRole|SessionRole|PublicRole => self::grantee($grantee, $identifiers), Tree::outer($list, ['grantee'])));
    }
}
