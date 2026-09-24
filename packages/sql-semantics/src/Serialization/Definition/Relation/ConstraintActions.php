<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Relation;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation\Constraint;
use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Serialization\Definition\Catalog\ObjectAddresses;
use SqlSemantics\Serialization\Definition\Constraints;
use SqlSemantics\Serialization\Definition\IndexKeys;
use SqlSemantics\Serialization\Definition\Storage;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes constraint additions, changes, validations, and removals.
 * @visibility SqlSemantics
 */
final class ConstraintActions
{
    /**
     * Returns null for actions outside the constraint family.
     */
    public static function write(RelationAction $action): ?Tree
    {
        $dialect = Dialect::PostgreSql;
        return match (true) {
            $action instanceof Constraint\AddConstraint => new Tree('add-constraint', [Build::keyword('ADD'), Constraints::write($action->constraint, $dialect), ...($action->notValid ? [Build::keyword('NOT VALID')] : [])]),
            $action instanceof Constraint\AddExclusionConstraint => new Tree('add-exclusion', [Build::keyword('ADD'), self::exclusion($action->constraint)]),
            $action instanceof Constraint\AddIndexConstraint => new Tree('add-index-constraint', [Build::keyword('ADD'), ...($action->name === null ? [] : [Build::keyword('CONSTRAINT'), Build::identifier([$action->name], $dialect)]), Build::keyword(($action->kind === \SqlSemantics\Schema\ConstraintKind::PrimaryKey ? 'PRIMARY KEY' : 'UNIQUE') . ' USING INDEX'), Build::identifier([$action->index], $dialect), Constraints::checking($action->checking)]),
            $action instanceof Constraint\AlterConstraint => new Tree('alter-constraint', [Build::keyword('ALTER CONSTRAINT'), Build::identifier([$action->name], $dialect), Constraints::checking($action->checking)]),
            $action instanceof Constraint\ValidateConstraint => new Tree('validate-constraint', [Build::keyword('VALIDATE CONSTRAINT'), Build::identifier([$action->name], $dialect)]),
            $action instanceof Constraint\DropConstraint => new Tree('drop-constraint', [Build::keyword('DROP CONSTRAINT' . ($action->ifExists ? ' IF EXISTS' : '')), Build::identifier([$action->name], $dialect), Build::keyword($action->behavior->value)]),
            default => null,
        };
    }

    /**
     * Writes the elements with their operators, then the index storage clauses and the predicate.
     */
    public static function exclusion(Constraint\ExclusionConstraint $constraint): Tree
    {
        $dialect = Dialect::PostgreSql;
        $elements = array_map(static fn (Constraint\ExclusionElement $element): Tree => new Tree('exclusion-element', [IndexKeys::write($element->key, $dialect), Build::keyword('WITH'), ObjectAddresses::operator($element->operator)]), $constraint->elements);
        return new Tree('exclusion', [
            ...($constraint->name === null ? [] : [Build::keyword('CONSTRAINT'), Build::identifier([$constraint->name], $dialect)]),
            Build::keyword('EXCLUDE'),
            ...($constraint->method === null ? [] : [Build::keyword('USING'), Build::identifier([$constraint->method], $dialect)]),
            Build::parentheses(Build::separated($elements)),
            ...($constraint->include === [] ? [] : [Build::keyword('INCLUDE'), Build::parentheses(Build::separated(array_map(static fn (string $column): Tree => Build::identifier([$column], $dialect), $constraint->include)))]),
            ...($constraint->parameters === [] ? [] : [Build::keyword('WITH'), Build::parentheses(Storage::parameters($constraint->parameters, $dialect))]),
            ...($constraint->tablespace === null ? [] : [Build::keyword('USING INDEX TABLESPACE'), Build::identifier([$constraint->tablespace], $dialect)]),
            ...($constraint->predicate === null ? [] : [Build::keyword('WHERE'), Build::parentheses(Expressions::write($constraint->predicate))]),
            Constraints::checking($constraint->checking),
        ]);
    }
}
