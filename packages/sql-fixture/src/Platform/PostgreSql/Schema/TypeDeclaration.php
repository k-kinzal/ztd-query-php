<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql\Schema;

use SqlFixture\Schema\TypeShape;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\ArrayOf;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\IntervalSpan;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\NamedOnPath;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Parameterized;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Statement\Type\TypeDescriptor;

/**
 * Reads the type of a column from the type the analysis resolved for it.
 *
 * Types are named after their catalog entries, as the catalog reader names
 * them, so INT and INT4 are INTEGER and DEC is NUMERIC. SERIAL and its
 * variants are the integer types they declare, fed by a sequence.
 *
 * @visibility root
 */
final class TypeDeclaration
{
    /**
     * The names of the sequence-backed pseudo types.
     */
    public const SERIALS = ['smallserial', 'serial2', 'serial', 'serial4', 'bigserial', 'serial8'];

    /**
     * Returns the type name and modifiers of the declared type; an array keeps the modifiers of its element type.
     */
    public function shape(TypeDescriptor $declared, TypeName $written): TypeShape
    {
        $element = $declared instanceof ArrayOf ? $declared->element : $declared;
        $numbers = match (true) {
            $element instanceof Parameterized => $element->modifiers,
            $element instanceof IntervalSpan => $element->precision === null ? [] : [$element->precision],
            $element instanceof NamedOnPath => $this->writtenModifiers($written),
            default => [],
        };

        return TypeShape::fromNumbers($this->name($declared), $numbers, $element instanceof Parameterized && $element->base === Builtin::Numeric, $this->serial($written));
    }

    /**
     * Names a resolved type after its catalog entry; an array is named after its element type.
     */
    public function name(TypeDescriptor $type): string
    {
        return match (true) {
            $type instanceof ArrayOf => $this->name($type->element) . '_ARRAY',
            $type instanceof Builtin => $this->catalogType($type->value),
            $type instanceof Parameterized => $this->catalogType($type->base->value),
            $type instanceof IntervalSpan => 'INTERVAL',
            $type instanceof NamedOnPath => $this->catalogType($type->name->name->value),
            default => strtoupper($type->name()),
        };
    }

    /**
     * Names a catalog type as the value generators know it: the SQL name of a built-in type, and the upper-cased name of any other.
     */
    public function catalogType(string $catalogName): string
    {
        return match ($catalogName) {
            'int2' => 'SMALLINT',
            'int4' => 'INTEGER',
            'int8' => 'BIGINT',
            'float4' => 'REAL',
            'float8' => 'DOUBLE PRECISION',
            'bool' => 'BOOLEAN',
            'bpchar' => 'CHAR',
            default => strtoupper($catalogName),
        };
    }

    /**
     * Returns the integer modifiers written after a type the analysis does not know.
     *
     * @return list<int>
     */
    public function writtenModifiers(TypeName $written): array
    {
        $designation = $written->designation;
        if (!$designation instanceof NamedDesignation) {
            return [];
        }
        $numbers = [];
        foreach ($designation->modifiers as $modifier) {
            $digits = $modifier instanceof Constant ? $modifier->integerValue() : null;
            if ($digits === null) {
                return [];
            }
            $numbers[] = (int) $digits;
        }

        return $numbers;
    }

    /**
     * Tells whether the column is written with SERIAL or one of its variants.
     */
    public function serial(TypeName $written): bool
    {
        $designation = $written->designation;

        return $written->array === null && $designation instanceof NamedDesignation && count($designation->name->parts) === 1
            && in_array($designation->catalogName()->value, self::SERIALS, true);
    }
}
