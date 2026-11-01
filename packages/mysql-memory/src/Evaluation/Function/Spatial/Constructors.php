<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Spatial;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Compile\Printer;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Value\Spatial\Geometry;
use MySqlMemory\Value\Spatial\Wkb;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Statement\Scalar;

/**
 * POINT and the geometry collection constructors.
 *
 * POINT converts its coordinates to doubles. The other constructors require GEOMETRY arguments
 * during resolution, before evaluation, and check component types and geometry structure when
 * evaluated. Any evaluated NULL makes the value NULL.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/gis-mysql-specific-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Constructors
{
    private const COLLECTIONS = ['LINESTRING' => 2, 'POLYGON' => 3, 'MULTIPOINT' => 4, 'MULTILINESTRING' => 5, 'MULTIPOLYGON' => 6, 'GEOMETRYCOLLECTION' => 7, 'GEOMCOLLECTION' => 7];

    /**
     * Answers the constructors and their argument counts.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        $routines = [new Routine('POINT', 2, 2, $this->point(...))];
        foreach (self::COLLECTIONS as $name => $type) {
            $routines[] = new Routine($name, $type === 7 ? 0 : 1, -1, fn (Frame $f, array $a): ?string => $this->collection($f, $a, $type, strtolower($name)));
        }

        return $routines;
    }

    /**
     * Refuses a non-geometric collection argument before any values are evaluated.
     *
     * @param list<Evaluable> $arguments The resolved arguments
     * @param list<Scalar> $written The corresponding expressions, for diagnostics
     */
    public static function validate(string $name, array $arguments, array $written): void
    {
        if (!isset(self::COLLECTIONS[strtoupper($name)])) {
            return;
        }
        foreach ($arguments as $index => $argument) {
            if ($argument->domain()->field !== Field::Geometry) {
                throw DataError::IllegalNonGeometric->error((new Printer())->expression($written[$index]));
            }
        }
    }

    /**
     * Makes the SRID-zero point of two double coordinates.
     *
     * @param list<Evaluable> $arguments The two coordinates
     */
    public function point(Frame $frame, array $arguments): ?string
    {
        $x = Convert::toDouble($arguments[0]->evaluate($frame), $arguments[0]->domain(), $frame->context);
        $y = Convert::toDouble($arguments[1]->evaluate($frame), $arguments[1]->domain(), $frame->context);

        return $x === null || $y === null ? null : Wkb::write(new Geometry(1, [[$x, $y]]));
    }

    /**
     * Makes a line, polygon or collection from the evaluated geometric members.
     *
     * @param list<Evaluable> $arguments The members
     */
    public function collection(Frame $frame, array $arguments, int $type, string $name): ?string
    {
        $legacy = $frame->context->modes->release === GrammarRelease::MySql5651;
        $children = [];
        foreach ($arguments as $argument) {
            $value = $argument->evaluate($frame);
            if ($value === null) {
                return null;
            }
            $child = Wkb::read((string) $value);
            if ($child === null) {
                throw DataError::GisInvalidData->error($name);
            }
            $expected = [2 => 1, 3 => 2, 4 => 1, 5 => 2, 6 => 3][$type] ?? $child->type;
            if ($child->type !== $expected) {
                if ($legacy) {
                    return null;
                }
                throw StatementError::WrongArguments->error($name);
            }
            $children[] = $child;
        }
        $geometry = $type === 2 ? new Geometry(2, array_map(static fn (Geometry $point): array => $point->points[0], $children)) : new Geometry($type, [], $children);
        if (!$geometry->valid($legacy)) {
            if ($legacy) {
                return null;
            }
            throw DataError::GisInvalidData->error($name);
        }

        return Wkb::write($geometry);
    }
}
