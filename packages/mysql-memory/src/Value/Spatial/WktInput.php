<?php

declare(strict_types=1);

namespace MySqlMemory\Value\Spatial;

/**
 * Reads two-dimensional WKT values independently of SQL syntax.
 *
 * The cursor advances through bounded recursive components. Legacy input permits
 * trailing bytes and bare MULTIPOINT members, but has no empty collection syntax.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/gis-wkt-functions.html.
 *
 * @visibility MySqlMemory
 */
final class WktInput
{
    private int $offset = 0;

    /**
     * @param string $text The runtime geometry value, never source SQL
     * @param bool $legacy Whether MySQL 5.6's value syntax is used
     * @param bool $modern Whether leading decimal dots and EMPTY are available (8.0 onward)
     */
    public function __construct(private readonly string $text, private readonly bool $legacy = false, private readonly bool $modern = true)
    {
    }

    /**
     * Reads a geometry and checks the complete representation in modern releases.
     */
    public function read(): ?Geometry
    {
        $geometry = $this->geometry(0);
        $this->space();
        return !$this->legacy && $this->offset !== strlen($this->text) ? null : $geometry;
    }

    /**
     * Reads a named component without accepting dimensional suffixes or extra ordinates.
     */
    public function geometry(int $depth): ?Geometry
    {
        $this->space();
        if ($depth >= 64 || preg_match('/\G[a-z]+/i', $this->text, $match, 0, $this->offset) !== 1) {
            return null;
        }
        $this->offset += strlen($match[0]);
        $type = array_search(strtoupper($match[0]), [1 => 'POINT', 2 => 'LINESTRING', 3 => 'POLYGON', 4 => 'MULTIPOINT', 5 => 'MULTILINESTRING', 6 => 'MULTIPOLYGON', 7 => 'GEOMETRYCOLLECTION'], true);
        if ($type === false) {
            return null;
        }
        $this->space();
        if ($type === 7 && !$this->legacy && $this->modern && strncasecmp(substr($this->text, $this->offset, 5), 'EMPTY', 5) === 0) {
            $this->offset += 5;
            return new Geometry(7);
        }
        return $this->body($type, $depth);
    }

    /**
     * Reads a homogeneous component body, retaining member and ring order.
     */
    public function body(int $type, int $depth): ?Geometry
    {
        if ($type < 1 || $type > 7 || $depth >= 64 || !$this->take('(')) {
            return null;
        }
        if ($type === 7 && !$this->legacy && $this->take(')')) {
            return new Geometry(7);
        }
        $this->space();
        $wrapped = !$this->legacy && ($this->text[$this->offset] ?? '') === '(';
        if ($type <= 2 || ($type === 4 && !$wrapped)) {
            return $this->ordinates($type);
        }
        $children = [];
        do {
            $child = $type === 7 ? $this->geometry($depth + 1) : $this->body([3 => 2, 4 => 1, 5 => 2, 6 => 3][$type], $depth + 1);
            if ($child === null || ($type === 3 && !$this->closed($child))) {
                return null;
            }
            $children[] = $child;
        } while ($this->take(','));
        return $this->take(')') ? new Geometry($type, [], $children) : null;
    }

    /**
     * Checks a ring's release-specific coordinate count and closure.
     */
    public function closed(Geometry $ring): bool
    {
        return count($ring->points) >= ($this->legacy ? 1 : 4) && $ring->points[0] === $ring->points[count($ring->points) - 1];
    }

    /**
     * Reads point, line or bare MULTIPOINT coordinates through the closing parenthesis.
     */
    public function ordinates(int $type): ?Geometry
    {
        $points = [];
        do {
            $point = $this->point();
            if ($point === null) {
                return null;
            }
            $points[] = $point;
        } while ($this->take(','));
        if (!$this->take(')') || ($type === 1 && count($points) !== 1) || ($type === 2 && count($points) < ($this->legacy ? 1 : 2))) {
            return null;
        }
        return $type === 4 ? new Geometry(4, [], array_map(static fn (array $point): Geometry => new Geometry(1, [$point]), $points)) : new Geometry($type, $points);
    }

    /**
     * @return array{float, float}|null A finite coordinate pair
     */
    public function point(): ?array
    {
        $x = $this->number();
        $y = $this->number();
        return $x === null || $y === null ? null : [$x, $y];
    }

    /**
     * Reads a signed decimal or exponent without swallowing a following ordinate sign.
     */
    public function number(): ?float
    {
        $this->space();
        if ((($this->legacy || !$this->modern) && ($this->text[$this->offset] ?? '') === '.') || preg_match('/\G[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?/', $this->text, $match, 0, $this->offset) !== 1) {
            return null;
        }
        $this->offset += strlen($match[0]);
        $value = (float) $match[0];
        return is_finite($value) ? $value : null;
    }

    /**
     * Consumes one punctuation character after optional whitespace.
     */
    public function take(string $character): bool
    {
        $this->space();
        if (($this->text[$this->offset] ?? '') !== $character) {
            return false;
        }
        ++$this->offset;
        return true;
    }

    /**
     * Advances over the whitespace permitted between WKT tokens.
     */
    public function space(): void
    {
        $this->offset += strspn($this->text, " \t\n\r\v\f", $this->offset);
    }
}
