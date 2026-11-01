<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Spatial;

use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Value\Spatial\Bounds;
use MySqlMemory\Value\Spatial\Geometry;
use MySqlMemory\Value\Spatial\Wkb;
use SqlSemantics\Contract\GrammarRelease;

/**
 * Evaluates the legacy CONTAINS spelling using minimum bounding rectangles.
 *
 * MySQL 5.6 includes boundaries and returns NULL for malformed encodings.
 * MySQL 5.7 requires interior intersection and reports geometry input errors.
 * Both arguments are evaluated before NULL or geometry validation is applied.
 * Source: https://docs.oracle.com/cd/E17952_01/mysql-5.7-en/spatial-relation-functions-mbr.html.
 *
 * @visibility MySqlMemory
 */
final class Relations
{
    /**
     * @return list<Routine> The legacy keyword function
     */
    public function routines(): array
    {
        return [new Routine('CONTAINS', 2, 2, $this->contains(...))];
    }

    /**
     * Evaluates the two envelopes under the release's containment relation.
     *
     * @param list<Evaluable> $arguments The two geometry operands
     * @throws \MySqlMemory\Error\SqlError When an evaluated geometry has an invalid encoding
     */
    public function contains(Frame $frame, array $arguments): ?int
    {
        $left = $arguments[0]->evaluate($frame);
        $right = $arguments[1]->evaluate($frame);
        if ($left === null || $right === null) {
            return null;
        }
        $legacy = $frame->context->modes->release === GrammarRelease::MySql5651;
        $first = $this->geometry((string) Convert::toText($left, $arguments[0]->domain()), $legacy);
        $second = $this->geometry((string) Convert::toText($right, $arguments[1]->domain()), $legacy);
        $bounds = new Bounds();
        $outer = $first === null ? null : $bounds->of($first);
        $inner = $second === null ? null : $bounds->of($second);
        return $outer === null || $inner === null ? null : (int) $bounds->contains($outer, $inner, $legacy);
    }

    /**
     * Reads coordinates with the release's byte-order and malformed-input behavior.
     *
     * @throws \MySqlMemory\Error\SqlError When MySQL 5.7 refuses the geometry encoding
     */
    public function geometry(string $bytes, bool $legacy): ?Geometry
    {
        if (!$legacy) {
            return (new Properties())->geometry($bytes, 'mbrcontains');
        }
        $offset = 4;
        $geometry = Wkb::geometry(substr_replace($bytes, "\1", 4, 1), $offset, 0, true);
        return $geometry === null || !(new Properties())->readable($geometry) ? null : $geometry;
    }
}
