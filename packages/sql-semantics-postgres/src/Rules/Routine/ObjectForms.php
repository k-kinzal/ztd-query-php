<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Routine;

use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectReference;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\CastPair;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\LargeObjectNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\MemberName;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\OperatorGroupName;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\RelationTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\TransformFor;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\TypeReference;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\UnqualifiedName;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\AggregateSignature;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\OperatorSignature;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\RoutineSignature;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Tells which object name forms each generic object command writes for each object kind.
 *
 * Rule: PG-OBJECT-FORM-001. Scope: `DropStmt`, `RemoveFuncStmt`,
 * `RemoveAggrStmt`, `RemoveOperStmt`, `DropOpClassStmt`, `DropOpFamilyStmt`,
 * `CommentStmt`, `SecLabelStmt`, `RenameStmt`, `AlterObjectSchemaStmt`,
 * `AlterOwnerStmt`, `AlterObjectDependsStmt` and the DROP CAST and DROP
 * TRANSFORM forms of the catalog family. The grammar fixes, per command and
 * object kind, how the object is named: a dotted name of any length, a single
 * name, a name within a table or domain, a type name, a routine, aggregate or
 * operator signature, a name with an access method, a cast, a transform, a
 * large-object number or a relation name. A constructor accepts exactly the
 * pairs these tables list, so every command it builds can be written back.
 * Source: the synopses of https://www.postgresql.org/docs/17/sql-commands.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ObjectForms
{
    /**
     * The kinds `object_type_any_name` writes with a dotted name.
     */
    public const ANY_NAME = ['TABLE', 'SEQUENCE', 'VIEW', 'MATERIALIZED VIEW', 'INDEX', 'FOREIGN TABLE', 'COLLATION', 'CONVERSION', 'STATISTICS', 'TEXT SEARCH PARSER', 'TEXT SEARCH DICTIONARY', 'TEXT SEARCH TEMPLATE', 'TEXT SEARCH CONFIGURATION'];

    /**
     * The kinds `drop_type_name` writes with a single name.
     */
    public const DROP_NAME = ['ACCESS METHOD', 'EVENT TRIGGER', 'EXTENSION', 'FOREIGN DATA WRAPPER', 'LANGUAGE', 'PUBLICATION', 'SCHEMA', 'SERVER'];

    /**
     * The kinds `object_type_name` adds to `drop_type_name`.
     */
    public const GLOBAL_NAME = ['DATABASE', 'ROLE', 'SUBSCRIPTION', 'TABLESPACE'];

    /**
     * The kinds named within a table: `object_type_name_on_any_name`.
     */
    public const MEMBER = ['POLICY', 'RULE', 'TRIGGER'];

    /**
     * The routine kinds written with `function_with_argtypes`.
     */
    public const ROUTINE = ['FUNCTION', 'PROCEDURE', 'ROUTINE'];

    /**
     * The kinds DROP removes one object of at a time.
     */
    public const SINGLE_DROP = ['POLICY', 'RULE', 'TRIGGER', 'OPERATOR CLASS', 'OPERATOR FAMILY', 'CAST', 'TRANSFORM'];

    /**
     * The kinds whose objects are relations the context can declare: a command naming one resolves it.
     */
    public const RELATION = ['TABLE', 'VIEW', 'MATERIALIZED VIEW', 'FOREIGN TABLE'];

    /**
     * The kinds ALTER ... RENAME, SET SCHEMA and DEPENDS ON EXTENSION write with a relation name; TABLE and FOREIGN TABLE accept ONLY.
     */
    public const ALTERED_RELATION = ['TABLE', 'FOREIGN TABLE', 'SEQUENCE', 'VIEW', 'MATERIALIZED VIEW', 'INDEX'];

    /**
     * Answers the form code of an object reference.
     */
    public function form(ObjectReference $object): string
    {
        return match (true) {
            $object instanceof DottedName => 'dotted',
            $object instanceof UnqualifiedName => 'name',
            $object instanceof MemberName => $object->domain ? 'domain-member' : ($object->owner instanceof QualifiedName ? 'relation-member' : 'member'),
            $object instanceof TypeReference => 'type',
            $object instanceof RoutineSignature => 'routine',
            $object instanceof AggregateSignature => 'aggregate',
            $object instanceof OperatorSignature => 'operator',
            $object instanceof OperatorGroupName => 'group',
            $object instanceof CastPair => 'cast',
            $object instanceof TransformFor => 'transform',
            $object instanceof LargeObjectNumber => 'large',
            $object instanceof RelationTarget => $object->relation->only ? 'only-relation' : 'relation',
            default => 'other',
        };
    }

    /**
     * Answers the forms DROP writes for a kind.
     *
     * @return list<string>
     */
    public function drop(ObjectKind $kind): array
    {
        $value = $kind->value;

        return match (true) {
            in_array($value, self::ANY_NAME, true) => ['dotted'],
            in_array($value, self::DROP_NAME, true) => ['name'],
            in_array($value, self::MEMBER, true) => ['member'],
            in_array($value, self::ROUTINE, true) => ['routine'],
            default => $this->common($kind),
        };
    }

    /**
     * Answers the forms COMMENT ON writes for a kind.
     *
     * @return list<string>
     */
    public function comment(ObjectKind $kind): array
    {
        $value = $kind->value;

        return match (true) {
            in_array($value, [...self::ANY_NAME, 'COLUMN'], true) => ['dotted'],
            in_array($value, [...self::DROP_NAME, ...self::GLOBAL_NAME], true) => ['name'],
            in_array($value, [...self::MEMBER, 'CONSTRAINT'], true) => ['member'],
            in_array($value, self::ROUTINE, true) => ['routine'],
            $kind === ObjectKind::DomainConstraint => ['domain-member'],
            $kind === ObjectKind::LargeObject => ['large'],
            default => $this->common($kind),
        };
    }

    /**
     * Answers the forms SECURITY LABEL writes for a kind.
     *
     * @return list<string>
     */
    public function label(ObjectKind $kind): array
    {
        $value = $kind->value;

        return match (true) {
            in_array($value, [...self::ANY_NAME, 'COLUMN'], true) => ['dotted'],
            in_array($value, [...self::DROP_NAME, ...self::GLOBAL_NAME], true) => ['name'],
            in_array($value, self::ROUTINE, true) => ['routine'],
            $kind === ObjectKind::LargeObject => ['large'],
            $kind === ObjectKind::Type, $kind === ObjectKind::Domain => ['type'],
            $kind === ObjectKind::Aggregate => ['aggregate'],
            default => [],
        };
    }

    /**
     * Answers the forms the type, aggregate, operator, operator class and family, cast and transform kinds share in DROP and COMMENT.
     *
     * @return list<string>
     */
    public function common(ObjectKind $kind): array
    {
        return match ($kind) {
            ObjectKind::Type, ObjectKind::Domain => ['type'],
            ObjectKind::Aggregate => ['aggregate'],
            ObjectKind::Operator => ['operator'],
            ObjectKind::OperatorClass, ObjectKind::OperatorFamily => ['group'],
            ObjectKind::Cast => ['cast'],
            ObjectKind::Transform => ['transform'],
            default => [],
        };
    }

    /**
     * Answers the forms ALTER ... RENAME, SET SCHEMA and OWNER TO write for a kind, by command.
     *
     * @param string $command `rename`, `schema` or `owner`
     *
     * @return list<string>
     */
    public function altered(ObjectKind $kind, string $command): array
    {
        $value = $kind->value;
        $dotted = ['rename' => ['COLLATION', 'CONVERSION', 'DOMAIN', 'STATISTICS', 'TEXT SEARCH PARSER', 'TEXT SEARCH DICTIONARY', 'TEXT SEARCH TEMPLATE', 'TEXT SEARCH CONFIGURATION', 'TYPE'],
            'owner' => ['COLLATION', 'CONVERSION', 'DOMAIN', 'TYPE', 'STATISTICS', 'TEXT SEARCH DICTIONARY', 'TEXT SEARCH CONFIGURATION']][$command] ?? ['COLLATION', 'CONVERSION', 'DOMAIN', 'STATISTICS', 'TEXT SEARCH PARSER', 'TEXT SEARCH DICTIONARY', 'TEXT SEARCH TEMPLATE', 'TEXT SEARCH CONFIGURATION', 'TYPE'];
        $names = ['rename' => ['DATABASE', 'FOREIGN DATA WRAPPER', 'LANGUAGE', 'PUBLICATION', 'SCHEMA', 'SERVER', 'SUBSCRIPTION', 'EVENT TRIGGER', 'TABLESPACE', 'ROLE'],
            'owner' => ['DATABASE', 'LANGUAGE', 'SCHEMA', 'TABLESPACE', 'FOREIGN DATA WRAPPER', 'SERVER', 'EVENT TRIGGER', 'PUBLICATION', 'SUBSCRIPTION']][$command] ?? ['EXTENSION'];

        return match (true) {
            in_array($value, $dotted, true) => ['dotted'],
            in_array($value, $names, true) => ['name'],
            in_array($value, self::ROUTINE, true) => ['routine'],
            $kind === ObjectKind::Aggregate => ['aggregate'],
            $kind === ObjectKind::OperatorClass, $kind === ObjectKind::OperatorFamily => ['group'],
            default => $this->alteredRest($kind, $command),
        };
    }

    /**
     * Answers the forms of the relation, operator, member and large-object kinds of ALTER ... RENAME, SET SCHEMA and OWNER TO.
     *
     * @param string $command `rename`, `schema` or `owner`
     *
     * @return list<string>
     */
    public function alteredRest(ObjectKind $kind, string $command): array
    {
        $relation = $command !== 'owner' && in_array($kind->value, self::ALTERED_RELATION, true) && !($command === 'schema' && $kind === ObjectKind::Index);

        return match (true) {
            $relation => $kind === ObjectKind::Table || $kind === ObjectKind::ForeignTable ? ['relation', 'only-relation'] : ['relation'],
            $command === 'rename' && in_array($kind->value, self::MEMBER, true) => ['relation-member'],
            $command !== 'rename' && $kind === ObjectKind::Operator => ['operator'],
            $command === 'owner' && $kind === ObjectKind::LargeObject => ['large'],
            default => [],
        };
    }

    /**
     * Answers the forms ALTER ... DEPENDS ON EXTENSION writes for a kind.
     *
     * @return list<string>
     */
    public function depends(ObjectKind $kind): array
    {
        return match ($kind) {
            ObjectKind::Function, ObjectKind::Procedure, ObjectKind::Routine => ['routine'],
            ObjectKind::Trigger => ['relation-member'],
            ObjectKind::MaterializedView, ObjectKind::Index => ['relation'],
            default => [],
        };
    }
}
