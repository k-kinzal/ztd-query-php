<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Constraint;
use SqlSemantics\Schema\TableConstraint;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes integrity conditions from the mandatory operands of each constraint type.
 *
 * @visibility SqlSemantics
 */
final class Constraints
{
    /**
     * Writes a named or unnamed integrity constraint.
     * @throws InvalidStructure
     */
    public static function write(TableConstraint $constraint, Dialect $dialect): Tree
    {
        $body = match (true) {
            $constraint instanceof Constraint\PrimaryKey,
            $constraint instanceof Constraint\UniqueKey => new Tree('key', [Build::keyword($constraint instanceof Constraint\PrimaryKey ? 'PRIMARY KEY' : 'UNIQUE'), ...($constraint instanceof Constraint\UniqueKey && !$constraint->nullsDistinct ? [Build::keyword('NULLS NOT DISTINCT')] : []), Build::parentheses(Build::separated(array_map(static fn ($key): Tree => IndexKeys::write($key, $dialect), $constraint->keys))), self::checking($constraint->checking)]),
            $constraint instanceof Constraint\Check => new Tree('check', [Build::keyword('CHECK'), Build::parentheses(Expressions::write($constraint->predicate)), ...($constraint->enforced ? [] : [Build::keyword('NOT ENFORCED')]), ...($constraint->noInherit ? [Build::keyword('NO INHERIT')] : [])]),
            $constraint instanceof Constraint\ForeignKey => self::foreignKey($constraint, $dialect),
            default => throw new InvalidStructure('Unclassified integrity constraint.'),
        };
        return new Tree('constraint', [...($constraint->name === null ? [] : [Build::keyword('CONSTRAINT'), Build::identifier([$constraint->name], $dialect)]), $body]);
    }

    /**
     * Writes the referenced key, matching mode and referential actions.
     */
    public static function foreignKey(Constraint\ForeignKey $key, Dialect $dialect): Tree
    {
        $parts = [Build::keyword('FOREIGN KEY'), self::columns($key->columns, $dialect), Build::keyword('REFERENCES'), Build::identifier($key->referencedTable->parts, $dialect)];
        if ($key->referencedColumns !== []) {
            $parts[] = self::columns($key->referencedColumns, $dialect);
        }
        if ($key->match !== Constraint\MatchMode::Simple) {
            $parts[] = Build::keyword('MATCH ' . strtoupper($key->match->value));
        }
        $parts[] = Build::keyword('ON DELETE ' . strtoupper(str_replace('-', ' ', $key->onDelete->value)));
        if ($key->deleteColumns !== []) {
            $parts[] = self::columns($key->deleteColumns, $dialect);
        }
        array_push($parts, Build::keyword('ON UPDATE ' . strtoupper(str_replace('-', ' ', $key->onUpdate->value))), self::checking($key->checking));
        return new Tree('foreign-key', $parts);
    }

    /**
     * Writes an integrity condition attached to one newly declared column.
     * @throws InvalidStructure
     */
    public static function column(TableConstraint $constraint, Dialect $dialect): Tree
    {
        if ($constraint instanceof Constraint\Check) {
            return self::write($constraint, $dialect);
        }
        if ($constraint instanceof Constraint\ForeignKey) {
            $body = self::foreignKey($constraint, $dialect);
            return new Tree('column-reference', [...($constraint->name === null ? [] : [Build::keyword('CONSTRAINT'), Build::identifier([$constraint->name], $dialect)]), ...array_slice($body->children, 2)]);
        }
        if ($constraint instanceof Constraint\PrimaryKey || $constraint instanceof Constraint\UniqueKey) {
            return new Tree('column-key', [...($constraint->name === null ? [] : [Build::keyword('CONSTRAINT'), Build::identifier([$constraint->name], $dialect)]), Build::keyword($constraint instanceof Constraint\PrimaryKey ? 'PRIMARY KEY' : 'UNIQUE'), self::checking($constraint->checking)]);
        }
        throw new InvalidStructure('Unclassified column constraint.');
    }

    /**
     * @param list<string> $columns
     */
    public static function columns(array $columns, Dialect $dialect): Tree
    {
        return Build::parentheses(Build::separated(array_map(static fn (string $name): Tree => Build::identifier([$name], $dialect), $columns)));
    }

    /**
     * Writes a nondefault constraint-checking schedule.
     */
    public static function checking(Constraint\CheckingTime $checking): Tree
    {
        return match ($checking) {
            Constraint\CheckingTime::Immediate => new Tree('checking', []),
            Constraint\CheckingTime::DeferrableImmediate => Build::keyword('DEFERRABLE INITIALLY IMMEDIATE'),
            Constraint\CheckingTime::DeferrableDeferred => Build::keyword('DEFERRABLE INITIALLY DEFERRED'),
        };
    }
}
