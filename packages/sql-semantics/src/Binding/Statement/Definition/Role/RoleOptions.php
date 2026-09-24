<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Role;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Role\ClearedPassword;
use SqlSemantics\Model\Definition\Role\ConnectionLimit;
use SqlSemantics\Model\Definition\Role\RoleAdmins;
use SqlSemantics\Model\Definition\Role\RoleAttribute;
use SqlSemantics\Model\Definition\Role\RoleCapability;
use SqlSemantics\Model\Definition\Role\RoleInvariant;
use SqlSemantics\Model\Definition\Role\RoleMembers;
use SqlSemantics\Model\Definition\Role\RoleMemberships;
use SqlSemantics\Model\Definition\Role\RolePassword;
use SqlSemantics\Model\Definition\Role\RoleSystemId;
use SqlSemantics\Model\Definition\Role\RoleValidity;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Reads role options in source order, classifying each keyword and identifier form.
 * @visibility SqlSemantics
 */
final class RoleOptions
{
    /**
     * A definition accepts every alteration option plus memberships, administrators, and SYSID.
     * @return list<RoleAttribute|RolePassword|ClearedPassword|ConnectionLimit|RoleValidity|RoleMembers|RoleMemberships|RoleAdmins|RoleSystemId>
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function definition(?Node $list, Identifiers $identifiers): array
    {
        if ($list === null) {
            return [];
        }
        $options = [];
        foreach (Tree::outer($list, ['CreateOptRoleElem']) as $element) {
            $alteration = Tree::child($element, ['AlterOptRoleElem']);
            $options[] = $alteration === null ? self::membership($element, $identifiers) : self::option($alteration, $identifiers);
        }
        self::distinct($options, $list);
        return $options;
    }

    /**
     * An alteration accepts attributes, credentials, limits, and the USER member list.
     * @return list<RoleAttribute|RolePassword|ClearedPassword|ConnectionLimit|RoleValidity|RoleMembers>
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function alteration(?Node $list, Identifiers $identifiers): array
    {
        if ($list === null) {
            return [];
        }
        $options = array_map(static fn (Node $element): RoleAttribute|RolePassword|ClearedPassword|ConnectionLimit|RoleValidity|RoleMembers => self::option($element, $identifiers), Tree::outer($list, ['AlterOptRoleElem']));
        self::distinct($options, $list);
        return $options;
    }

    /**
     * The server rejects a repeated or contradicted option as conflicting.
     * @param list<RoleAttribute|RolePassword|ClearedPassword|ConnectionLimit|RoleValidity|RoleMembers|RoleMemberships|RoleAdmins|RoleSystemId> $options
     * @throws InvalidSql
     */
    public static function distinct(array $options, Node $list): void
    {
        if (RoleInvariant::conflicting($options)) {
            throw new InvalidSql(InputViolation::RoleOption, $list);
        }
    }

    /**
     * Reads the definition-only elements: SYSID, ADMIN, ROLE, and IN ROLE or IN GROUP.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function membership(Node $element, Identifiers $identifiers): RoleMembers|RoleMemberships|RoleAdmins|RoleSystemId
    {
        return match ($element->tokens()[0]->name) {
            'SYSID' => new RoleSystemId(self::integer(Tree::child($element, ['Iconst']) ?? $element, 0)),
            'ROLE' => new RoleMembers(RoleSpecs::references(self::roles($element), $identifiers)),
            'IN_P' => new RoleMemberships(RoleSpecs::references(self::roles($element), $identifiers)),
            'ADMIN' => new RoleAdmins(RoleSpecs::references(self::roles($element), $identifiers)),
            default => throw new UnclassifiedSql('Unclassified role definition option: ' . Tree::text($element)),
        };
    }

    /**
     * Reads one alteration element; USER adds members, the same request as ALTER GROUP ADD USER.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function option(Node $element, Identifiers $identifiers): RoleAttribute|RolePassword|ClearedPassword|ConnectionLimit|RoleValidity|RoleMembers
    {
        $tokens = $element->tokens();
        $first = $tokens[0];
        return match ($first->name) {
            'PASSWORD' => ($tokens[1]->name ?? '') === 'NULL_P' ? new ClearedPassword() : new RolePassword(self::text($element, $identifiers)),
            'ENCRYPTED' => new RolePassword(self::text($element, $identifiers)),
            'UNENCRYPTED' => throw new InvalidSql(InputViolation::RolePasswordEncryption, $element),
            'INHERIT' => new RoleAttribute(RoleCapability::Inherit, true),
            'CONNECTION' => new ConnectionLimit(self::integer(Tree::child($element, ['SignedIconst']) ?? $element, -1)),
            'VALID' => new RoleValidity(self::text($element, $identifiers)),
            'USER' => new RoleMembers(RoleSpecs::references(self::roles($element), $identifiers)),
            'IDENT' => self::attribute($first, $identifiers, $element),
            default => throw new UnclassifiedSql('Unclassified role option: ' . Tree::text($element)),
        };
    }

    /**
     * Identifier attributes match only their lowercase spelling, as the server compares them.
     * @throws InvalidSql
     */
    public static function attribute(Token $token, Identifiers $identifiers, Node $element): RoleAttribute
    {
        $name = $identifiers->name($token);
        $granted = !str_starts_with($name, 'no');
        $capability = $name === strtolower($name) ? RoleCapability::tryFrom(strtoupper($granted ? $name : substr($name, 2))) : null;
        if ($capability === null) {
            throw new InvalidSql(InputViolation::RoleOption, $element);
        }
        return new RoleAttribute($capability, $granted);
    }

    /**
     * Reads a signed integer constant within the 32-bit range the server stores.
     * @throws InvalidSql
     */
    public static function integer(Node $number, int $minimum): int
    {
        $text = str_replace(' ', '', Tree::text($number));
        $digits = ltrim($text, '+-');
        if (!ctype_digit($digits) || strlen(ltrim($digits, '0')) > 10 || (int) $digits > 2147483647) {
            throw new InvalidSql(InputViolation::RoleNumericOption, $number);
        }
        $value = str_starts_with($text, '-') ? -(int) $digits : (int) $digits;
        if ($value < $minimum) {
            throw new InvalidSql(InputViolation::RoleNumericOption, $number);
        }
        return $value;
    }

    /**
     * Passwords and validity bounds are text constants kept with their spelling.
     * @throws UnclassifiedSql
     */
    public static function text(Node $element, Identifiers $identifiers): Literal
    {
        $constant = Tree::child($element, ['Sconst']) ?? throw new UnclassifiedSql('A role option requires its text constant.');
        $literal = (new LiteralBinder($identifiers->dialect))->bind($constant->tokens()[0]);
        if (!$literal instanceof Literal || $literal->literalKind !== LiteralKind::Text) {
            throw new UnclassifiedSql('A role option requires a text constant: ' . Tree::text($constant));
        }
        return $literal;
    }

    /**
     * @throws UnclassifiedSql
     */
    public static function roles(Node $element): Node
    {
        return Tree::child($element, ['role_list']) ?? throw new UnclassifiedSql('A role membership option requires its role list.');
    }
}
