<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Write\Policy\ConstraintResponse;
use SqlSemantics\Schema\Column;
use SqlSemantics\Schema\ColumnDefinition;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\TypeDeclaration;

/**
 * Writes a column using its own typed value source and attributes.
 *
 * @visibility SqlSemantics
 */
final class Columns
{
    /**
     * Writes declaration-level nullability and generation behavior; a MySQL generated column writes its expression before the other attributes, as its grammar requires,
     * and a nullable MySQL AUTO_INCREMENT column writes NULL after AUTO_INCREMENT, because the later of the two decides.
     * A column MySQL SERIAL DEFAULT VALUE declared writes that attribute, which stands for NOT NULL, AUTO_INCREMENT and
     * the unique key its caller leaves out, and writes AUTO_INCREMENT once that key is gone; a column the SERIAL type
     * declared writes that type in place of BIGINT UNSIGNED NOT NULL AUTO_INCREMENT and the same key.
     * @param ConstraintResponse $keyConflict ON CONFLICT resolution of the SQLite primary key an AUTOINCREMENT column declares
     * @param bool $serialKey Whether the unique key SERIAL DEFAULT VALUE or the SERIAL type declares is still present
     */
    public static function write(ColumnDefinition $column, Dialect $dialect, ConstraintResponse $keyConflict = ConstraintResponse::Default, bool $serialKey = true): Tree
    {
        $generation = $column->generation;
        $serial = $serialKey && $generation instanceof Column\AutoIncrementColumn && ($generation->serialDefault || $generation->serialType);
        $serialType = $serial && $generation->serialType;
        $computed = $dialect === Dialect::MySql && $generation instanceof Column\ComputedColumn ? [Build::keyword('GENERATED ALWAYS AS'), Build::parentheses(Expressions::write($generation->expression)), Build::keyword(strtoupper($generation->storage->value))] : [];
        $parts = [Build::identifier([$column->name], $dialect), $serialType ? Build::keyword('SERIAL') : self::type($column), ...$computed, ColumnAttributes::write($column->attributes, $dialect)];
        if ($column->nullability === \SqlSemantics\Type\Nullability::NotNull && !$serial && !$generation instanceof Column\SerialColumn) {
            array_push($parts, Build::keyword('NOT NULL'), ...Constraints::resolution($column->nullConflict));
        }
        $trailingNull = $column->nullDeclared && $dialect === Dialect::MySql && $generation instanceof Column\AutoIncrementColumn;
        if ($column->nullDeclared && !$trailingNull) {
            $parts[] = Build::keyword('NULL');
        }
        array_push($parts, ...($serialType ? [] : self::generation($generation, $dialect, $keyConflict, $serial, $computed === [])));
        if ($trailingNull) {
            $parts[] = Build::keyword('NULL');
        }
        return new Tree('column', $parts);
    }

    /**
     * Writes the value source after the nullability: a default and ON UPDATE expression, a generation expression not
     * already written after the type, an identity, or AUTO_INCREMENT in the dialect's spelling.
     * @param bool $serial Whether the column writes MySQL SERIAL DEFAULT VALUE in place of AUTO_INCREMENT
     * @param bool $computedPending Whether a generation expression has not been written after the type
     * @return list<Tree>
     */
    public static function generation(Column\Generation $generation, Dialect $dialect, ConstraintResponse $keyConflict, bool $serial, bool $computedPending): array
    {
        return match (true) {
            $generation instanceof Column\SuppliedColumn => [
                ...($generation->default === null ? [] : [Build::keyword('DEFAULT'), self::defaultValue($generation->default, $dialect)]),
                ...($generation->onUpdate === null ? [] : [Build::keyword('ON UPDATE'), Expressions::write($generation->onUpdate)]),
            ],
            $generation instanceof Column\ComputedColumn && $computedPending => [Build::keyword('GENERATED ALWAYS AS'), Build::parentheses(Expressions::write($generation->expression)), Build::keyword(strtoupper($generation->storage->value))],
            $generation instanceof Column\IdentityColumn => [Build::keyword('GENERATED ' . ($generation->mode === Column\IdentityMode::Always ? 'ALWAYS' : 'BY DEFAULT') . ' AS IDENTITY'), Sequence::write($generation->sequence)],
            $generation instanceof Column\AutoIncrementColumn => $dialect === Dialect::Sqlite ? [Build::keyword('PRIMARY KEY'), ...Constraints::resolution($keyConflict), Build::keyword('AUTOINCREMENT')] : [Build::keyword($serial ? 'SERIAL DEFAULT VALUE' : 'AUTO_INCREMENT')],
            default => [],
        };
    }

    /**
     * Writes the column type; a serial column writes the serial type of its integer width, which declares its
     * sequence default and NOT NULL.
     */
    public static function type(ColumnDefinition $column): Tree
    {
        $identity = $column->type->identity;
        if (!$column->generation instanceof Column\SerialColumn || !$identity instanceof \SqlSemantics\Type\Identity\Numeric\IntegerStorage) {
            return TypeDeclaration::write($column->type);
        }
        return Build::keyword(match ($identity->base) {
            \SqlSemantics\Type\Identity\BuiltinIdentity::SmallInt => 'smallserial',
            \SqlSemantics\Type\Identity\BuiltinIdentity::BigInt => 'bigserial',
            default => 'serial',
        });
    }

    /**
     * Writes a column default: MySQL and SQLite take literals, signed numbers and the current-time keywords bare and any other expression in parentheses.
     */
    public static function defaultValue(\SqlSemantics\Model\Expression $default, Dialect $dialect): Tree
    {
        if ($dialect === Dialect::PostgreSql) {
            return Expressions::write($default);
        }
        $keywords = $dialect === Dialect::MySql ? ['CURRENT_TIMESTAMP', 'LOCALTIME', 'LOCALTIMESTAMP'] : ['CURRENT_DATE', 'CURRENT_TIME', 'CURRENT_TIMESTAMP'];
        if ($default instanceof \SqlSemantics\Model\Scalar\Value\ContextReference && in_array($default->request->value, $keywords, true)) {
            return Expressions::write($default);
        }
        return MySqlTable\ColumnChanges::default($default);
    }

    /**
     * Returns the unique key MySQL SERIAL DEFAULT VALUE or the SERIAL type declared on the column, which the column
     * writes with that attribute or type: the first unnamed key on the column alone without key options; null for any
     * other column, and for a SERIAL column whose type is no longer an unsigned BIGINT.
     * @param list<\SqlSemantics\Schema\TableConstraint> $constraints
     */
    public static function serialKey(ColumnDefinition $column, array $constraints): ?\SqlSemantics\Schema\Constraint\UniqueKey
    {
        $identity = $column->type->identity;
        $serialType = $identity instanceof \SqlSemantics\Type\Identity\Numeric\IntegerStorage && $identity->base === \SqlSemantics\Type\Identity\BuiltinIdentity::BigInt && $identity->unsigned && $identity->displayWidth === null && !$column->attributes->zeroFill;
        if (!$column->generation instanceof Column\AutoIncrementColumn || !$column->generation->serialDefault && !($column->generation->serialType && $serialType)) {
            return null;
        }
        foreach ($constraints as $constraint) {
            $key = $constraint instanceof \SqlSemantics\Schema\Constraint\UniqueKey && $constraint->name === null && count($constraint->keys) === 1 ? $constraint->keys[0] : null;
            if ($key instanceof \SqlSemantics\Schema\Index\ColumnKey && $key->prefixLength === null && $key->direction === null && $constraint->localColumns() === [$column->name] && $constraint->index->name === null && $constraint->index->method === null && Indexes::options($constraint->index->properties, Dialect::MySql)->toString() === '') {
                return $constraint;
            }
        }
        return null;
    }

    /**
     * Leaves out the unique keys that SERIAL DEFAULT VALUE declared on the given columns.
     * @param list<ColumnDefinition> $columns
     * @param list<\SqlSemantics\Schema\TableConstraint> $constraints
     * @return list<\SqlSemantics\Schema\TableConstraint>
     */
    public static function unserial(array $columns, array $constraints): array
    {
        foreach ($columns as $column) {
            $key = self::serialKey($column, $constraints);
            $constraints = array_values(array_filter($constraints, static fn (\SqlSemantics\Schema\TableConstraint $constraint): bool => $constraint !== $key));
        }
        return $constraints;
    }
}
