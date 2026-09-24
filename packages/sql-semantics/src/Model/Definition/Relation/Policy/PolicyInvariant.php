<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Policy;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\PublicRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * The identity, role, and expression rules shared by policy definitions and alterations.
 * @visibility SqlSemantics
 */
final class PolicyInvariant
{
    /**
     * A policy is a PostgreSQL object with a nonempty name and PostgreSQL expressions.
     * @throws InvalidStructure
     */
    public static function identity(Origin $origin, string $name, ?Expression $using, ?Expression $check): void
    {
        foreach ([$using, $check] as $expression) {
            if ($expression !== null && $expression->type->dialect !== Dialect::PostgreSql) {
                throw new InvalidStructure('A policy expression must use the PostgreSQL dialect.');
            }
        }
        if ($origin->dialect !== Dialect::PostgreSql || $name === '') {
            throw new InvalidStructure('A policy requires PostgreSQL and a nonempty name.');
        }
    }

    /**
     * The roles a policy applies to form a nonempty list.
     * @param list<NamedRole|SessionRole|PublicRole> $roles
     * @return non-empty-list<NamedRole|SessionRole|PublicRole>
     * @throws InvalidStructure
     */
    public static function roles(array $roles): array
    {
        Collections::alternatives($roles, [NamedRole::class, SessionRole::class, PublicRole::class]);
        return Collections::nonEmpty($roles);
    }

    /**
     * SELECT and DELETE policies have no WITH CHECK expression and INSERT policies no USING expression.
     * @throws InvalidStructure
     */
    public static function expressions(PolicyCommand $command, ?Expression $using, ?Expression $check): void
    {
        if (($command === PolicyCommand::Insert && $using !== null) || (in_array($command, [PolicyCommand::Select, PolicyCommand::Delete], true) && $check !== null)) {
            throw new InvalidStructure('SELECT and DELETE policies take only USING and INSERT policies only WITH CHECK.');
        }
    }
}
