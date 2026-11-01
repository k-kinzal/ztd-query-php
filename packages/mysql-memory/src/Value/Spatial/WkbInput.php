<?php

declare(strict_types=1);

namespace MySqlMemory\Value\Spatial;

/**
 * Reads constructor WKB, checking component counts and ring closure.
 *
 * Nonfinite binary ordinates are retained. MySQL 5.6 accepts trailing bytes and
 * any nonzero byte-order marker as little endian; modern inputs are complete.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/gis-wkb-functions.html.
 *
 * @visibility MySqlMemory
 */
final class WkbInput
{
    /**
     * Reads external WKB or the geometry-typed input accepted by legacy constructors.
     */
    public function read(string $bytes, bool $legacy = false, bool $internal = false): ?Geometry
    {
        $offset = $internal ? 4 : 0;
        $geometry = Wkb::geometry($bytes, $offset, 0, legacyByteOrder: $legacy);
        if ($geometry === null || (!$legacy && $offset !== strlen($bytes)) || !$this->wellFormed($geometry, $legacy)) {
            return null;
        }
        return $geometry;
    }

    /**
     * Validates representation without imposing geometric topology or finite point coordinates.
     */
    public function wellFormed(Geometry $geometry, bool $legacy): bool
    {
        if ($geometry->type <= 2) {
            return $geometry->type === 1 ? count($geometry->points) === 1 : count($geometry->points) >= ($legacy ? 1 : 2);
        }
        if ($geometry->type !== 7 && $geometry->children === []) {
            return false;
        }
        foreach ($geometry->children as $child) {
            $expected = [3 => 2, 4 => 1, 5 => 2, 6 => 3][$geometry->type] ?? $child->type;
            if ($child->type !== $expected || !$this->wellFormed($child, $legacy)) {
                return false;
            }
            if ($geometry->type === 3 && (count($child->points) < ($legacy ? 1 : 4) || $child->points[0] !== $child->points[count($child->points) - 1])) {
                return false;
            }
        }
        return true;
    }
}
