<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Write\Policy\ConstraintResponse;
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
            $constraint instanceof Constraint\UniqueKey => new Tree('key', [Build::keyword($constraint instanceof Constraint\PrimaryKey ? 'PRIMARY KEY' : 'UNIQUE'), ...self::keyHead($constraint, $dialect), Build::parentheses(Build::separated(array_map(static fn ($key): Tree => IndexKeys::write($key, $dialect), $constraint->keys))), ...self::keyTail($constraint->index, $dialect), self::checking($constraint->checking), ...self::resolution($constraint->onConflict)]),
            $constraint instanceof Constraint\Check => new Tree('check', [Build::keyword('CHECK'), Build::parentheses(Expressions::write($constraint->predicate)), ...($constraint->enforced ? [] : [Build::keyword('NOT ENFORCED')]), ...($constraint->noInherit ? [Build::keyword('NO INHERIT')] : []), ...self::resolution($constraint->onConflict)]),
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
        $parts = [Build::keyword('FOREIGN KEY'), ...($key->indexName === null ? [] : [Build::identifier([$key->indexName], $dialect)]), self::columns($key->columns, $dialect), Build::keyword('REFERENCES'), Build::identifier($key->referencedTable->parts, $dialect)];
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
            if ($constraint->onConflict !== ConstraintResponse::Default) {
                throw new InvalidStructure('A column-level CHECK does not declare an ON CONFLICT resolution.');
            }
            return self::write($constraint, $dialect);
        }
        if ($constraint instanceof Constraint\ForeignKey) {
            if ($constraint->indexName !== null) {
                throw new InvalidStructure('A column-level reference does not name an index.');
            }
            $body = self::foreignKey($constraint, $dialect);
            return new Tree('column-reference', [...($constraint->name === null ? [] : [Build::keyword('CONSTRAINT'), Build::identifier([$constraint->name], $dialect)]), ...array_slice($body->children, 2)]);
        }
        if ($constraint instanceof Constraint\PrimaryKey || $constraint instanceof Constraint\UniqueKey) {
            return new Tree('column-key', [...($constraint->name === null ? [] : [Build::keyword('CONSTRAINT'), Build::identifier([$constraint->name], $dialect)]), Build::keyword($constraint instanceof Constraint\PrimaryKey ? 'PRIMARY KEY' : 'UNIQUE'), ...($constraint instanceof Constraint\UniqueKey && !$constraint->nullsDistinct ? [Build::keyword('NULLS NOT DISTINCT')] : []), ...self::direction($constraint, $dialect), ...self::columnIndex($constraint->index, $dialect), self::checking($constraint->checking), ...self::resolution($constraint->onConflict)]);
        }
        throw new InvalidStructure('Unclassified column constraint.');
    }

    /**
     * Writes the direction of a SQLite column-level key, the only dialect whose column constraint takes one.
     * @return list<Tree>
     */
    public static function direction(Constraint\PrimaryKey|Constraint\UniqueKey $constraint, Dialect $dialect): array
    {
        $direction = $constraint->keys[0]->direction;
        return $dialect === Dialect::Sqlite && $constraint instanceof Constraint\PrimaryKey && $direction !== null ? [Build::keyword($direction->value)] : [];
    }

    /**
     * @param list<string> $columns
     */
    public static function columns(array $columns, Dialect $dialect): Tree
    {
        return Build::parentheses(Build::separated(array_map(static fn (string $name): Tree => Build::identifier([$name], $dialect), $columns)));
    }

    /**
     * Writes what precedes the key columns: the MySQL index name and method, or PostgreSQL's NULLS NOT DISTINCT.
     * @return list<Tree>
     */
    public static function keyHead(Constraint\PrimaryKey|Constraint\UniqueKey $constraint, Dialect $dialect): array
    {
        $index = $constraint->index;
        $parts = $constraint instanceof Constraint\UniqueKey && !$constraint->nullsDistinct ? [Build::keyword('NULLS NOT DISTINCT')] : [];
        if ($index->name !== null) {
            array_push($parts, Build::keyword('KEY'), Build::identifier([$index->name], $dialect));
        }
        if ($index->method !== null) {
            array_push($parts, Build::keyword('USING ' . strtoupper($index->method)));
        }
        return $parts;
    }

    /**
     * Writes what follows the key columns: MySQL index options, or PostgreSQL INCLUDE, WITH and USING INDEX TABLESPACE.
     * @return list<Tree>
     */
    public static function keyTail(Constraint\KeyIndex $index, Dialect $dialect): array
    {
        $parts = $index->include === [] ? [] : [Build::keyword('INCLUDE'), self::columns($index->include, $dialect)];
        return [...$parts, ...self::columnIndex($index, $dialect)];
    }

    /**
     * Writes the index options a column-level key may declare; a column-level key has no covering columns.
     * @return list<Tree>
     */
    public static function columnIndex(Constraint\KeyIndex $index, Dialect $dialect): array
    {
        $properties = $index->properties;
        if ($dialect !== Dialect::PostgreSql) {
            return [Indexes::options($properties, $dialect)];
        }
        $parts = $properties->storageParameters === [] ? [] : [Build::keyword('WITH'), Build::parentheses(Storage::parameters($properties->storageParameters, $dialect))];
        return [...$parts, ...($properties->tablespace === null ? [] : [Build::keyword('USING INDEX TABLESPACE'), Build::identifier([$properties->tablespace], $dialect)])];
    }

    /**
     * Writes a declared SQLite ON CONFLICT resolution; Default writes nothing.
     * @return list<Tree>
     */
    public static function resolution(ConstraintResponse $resolution): array
    {
        return $resolution === ConstraintResponse::Default ? [] : [Build::keyword('ON CONFLICT ' . $resolution->value)];
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
