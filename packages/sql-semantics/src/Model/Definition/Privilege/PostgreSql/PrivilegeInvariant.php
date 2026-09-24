<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Privilege\PostgreSql;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\PublicRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Enforces the privilege domains PostgreSQL defines for each object class.
 * @visibility SqlSemantics
 */
final class PrivilegeInvariant
{
    /**
     * Privilege operations exist only in PostgreSQL.
     * @throws InvalidStructure
     */
    public static function dialect(Origin $origin): void
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('Privilege operations require PostgreSQL.');
        }
    }

    /**
     * ALL PRIVILEGES cannot be combined with individual privileges.
     * @param list<ObjectPrivilege|ColumnPrivilege> $privileges Ordered privilege requests
     * @throws InvalidStructure
     */
    public static function privileges(array $privileges): void
    {
        Collections::alternatives(Collections::nonEmpty($privileges), [ObjectPrivilege::class, ColumnPrivilege::class]);
        foreach ($privileges as $privilege) {
            if ($privilege->privilege === Privilege::All && count($privileges) !== 1) {
                throw new InvalidStructure('ALL PRIVILEGES must be the only privilege request.');
            }
        }
    }

    /**
     * @param list<NamedRole|SessionRole|PublicRole> $grantees Recipient roles
     * @throws InvalidStructure
     */
    public static function grantees(array $grantees, bool $public): void
    {
        Collections::alternatives(Collections::nonEmpty($grantees), $public ? [NamedRole::class, SessionRole::class, PublicRole::class] : [NamedRole::class, SessionRole::class]);
    }

    /**
     * Diagnoses a privilege outside the object class domain or columns on a non-table class.
     * @param non-empty-list<ObjectPrivilege|ColumnPrivilege> $privileges Ordered privilege requests
     */
    public static function mismatch(Target\TableTargets|Target\SchemaObjectTargets|Target\ServerObjectTargets|Target\RoutineTargets|Target\LargeObjectTargets|Target\ParameterTargets|Target\SchemaScopedTargets|DefaultPrivilegeTarget $target, array $privileges): ?InputViolation
    {
        $domain = self::domain($target);
        $columnar = $target instanceof Target\TableTargets || ($target instanceof Target\SchemaScopedTargets && $target->class === Target\SchemaScopedClass::Tables);
        foreach ($privileges as $privilege) {
            if ($privilege instanceof ColumnPrivilege && !$columnar) {
                return InputViolation::ColumnPrivilege;
            }
            if ($privilege->privilege !== Privilege::All && !in_array($privilege->privilege, $domain, true)) {
                return InputViolation::PrivilegeTarget;
            }
        }
        return null;
    }

    /**
     * Rejects privilege requests the object class cannot carry.
     * @param non-empty-list<ObjectPrivilege|ColumnPrivilege> $privileges Ordered privilege requests
     * @throws InvalidStructure
     */
    public static function target(Origin $origin, Target\TableTargets|Target\SchemaObjectTargets|Target\ServerObjectTargets|Target\RoutineTargets|Target\LargeObjectTargets|Target\ParameterTargets|Target\SchemaScopedTargets|DefaultPrivilegeTarget $target, array $privileges): void
    {
        self::dialect($origin);
        self::privileges($privileges);
        $violation = self::mismatch($target, $privileges);
        if ($violation !== null) {
            throw new InvalidStructure($violation->message());
        }
    }

    /**
     * Lists the privilege types PostgreSQL accepts for the object class.
     * @return list<Privilege>
     */
    public static function domain(Target\TableTargets|Target\SchemaObjectTargets|Target\ServerObjectTargets|Target\RoutineTargets|Target\LargeObjectTargets|Target\ParameterTargets|Target\SchemaScopedTargets|DefaultPrivilegeTarget $target): array
    {
        return match (true) {
            $target instanceof Target\TableTargets => self::relation(true),
            $target instanceof Target\RoutineTargets => [Privilege::Execute],
            $target instanceof Target\LargeObjectTargets => [Privilege::Select, Privilege::Update],
            $target instanceof Target\ParameterTargets => [Privilege::Set, Privilege::AlterSystem],
            $target instanceof Target\SchemaObjectTargets => $target->class === Target\SchemaObjectClass::Sequence ? self::sequence() : [Privilege::Usage],
            $target instanceof Target\ServerObjectTargets => self::server($target->class),
            $target instanceof Target\SchemaScopedTargets => self::scoped($target->class),
            $target instanceof DefaultPrivilegeTarget => self::defaulted($target),
        };
    }

    /**
     * A bare relation target may name a sequence, so USAGE is admitted until the object class is known.
     * @return list<Privilege>
     */
    public static function relation(bool $sequences): array
    {
        $relation = [Privilege::Select, Privilege::Insert, Privilege::Update, Privilege::Delete, Privilege::Truncate, Privilege::References, Privilege::Trigger, Privilege::Maintain];
        return $sequences ? [...$relation, Privilege::Usage] : $relation;
    }

    /**
     * @return list<Privilege>
     */
    public static function sequence(): array
    {
        return [Privilege::Usage, Privilege::Select, Privilege::Update];
    }

    /**
     * @return list<Privilege>
     */
    public static function server(Target\ServerObjectClass $class): array
    {
        return match ($class) {
            Target\ServerObjectClass::Database => [Privilege::Create, Privilege::Connect, Privilege::Temporary],
            Target\ServerObjectClass::Schema => [Privilege::Create, Privilege::Usage],
            Target\ServerObjectClass::Tablespace => [Privilege::Create],
            Target\ServerObjectClass::ForeignDataWrapper, Target\ServerObjectClass::ForeignServer, Target\ServerObjectClass::Language => [Privilege::Usage],
        };
    }

    /**
     * @return list<Privilege>
     */
    public static function scoped(Target\SchemaScopedClass $class): array
    {
        return match ($class) {
            Target\SchemaScopedClass::Tables => self::relation(true),
            Target\SchemaScopedClass::Sequences => self::sequence(),
            Target\SchemaScopedClass::Functions, Target\SchemaScopedClass::Procedures, Target\SchemaScopedClass::Routines => [Privilege::Execute],
        };
    }

    /**
     * @return list<Privilege>
     */
    public static function defaulted(DefaultPrivilegeTarget $target): array
    {
        return match ($target) {
            DefaultPrivilegeTarget::Tables => self::relation(false),
            DefaultPrivilegeTarget::Sequences => self::sequence(),
            DefaultPrivilegeTarget::Functions => [Privilege::Execute],
            DefaultPrivilegeTarget::Types => [Privilege::Usage],
            DefaultPrivilegeTarget::Schemas => [Privilege::Create, Privilege::Usage],
        };
    }

    /**
     * Default privileges accept no column lists and no schema selection for SCHEMAS.
     * @param non-empty-list<ObjectPrivilege|ColumnPrivilege> $privileges Ordered privilege requests
     * @param list<NamedRole|SessionRole> $roles Defining roles
     * @param list<string> $schemas Schema selection
     * @throws InvalidStructure
     */
    public static function defaults(Origin $origin, DefaultPrivilegeTarget $target, array $privileges, array $roles, array $schemas): void
    {
        self::target($origin, $target, $privileges);
        Collections::alternatives($roles, [NamedRole::class, SessionRole::class]);
        Collections::strings($schemas);
        if (in_array('', $schemas, true) || ($schemas !== [] && $target === DefaultPrivilegeTarget::Schemas)) {
            throw new InvalidStructure('Default privileges on schemas cannot select schemas, and schema names must be nonempty.');
        }
    }
}
