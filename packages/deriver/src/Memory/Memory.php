<?php

declare(strict_types=1);

namespace Deriver\Memory;

use Deriver\Value\Arrays;
use Deriver\Value\Term;

/**
 * Copy-on-write cells, object records, globals, and static storage.
 * @visibility root
 */
final class Memory
{
    /**
     * @var array<string, Term> Versioned storage roots.
     */
    public array $cells = [];
    /**
     * @var array<string, string> Runtime object class identities.
     */
    public array $classes = [];
    /**
     * @var array<string, array<string, string>> Declared property types by storage root and slot.
     */
    public array $propertyTypes = [];
    /**
     * @var array<string, int> Cell write versions.
     */
    public array $versions = [];
    /**
     * @var array<string, string> Last explained write by memory root.
     */
    public array $writers = [];
    /**
     * @var array<string, array<int|string, true>> Readonly slots eligible for one write during clone.
     */
    public array $cloneWrites = [];
    /**
     * @var array<string, \Deriver\Model\State\StateSlot> Registered abstract object lifecycle contracts.
     */
    public array $slotContracts = [];
    /**
     * Allocation and evaluation event sequence.
     */
    public int $sequence = 0;
    /**
     * @var array<string, LiveArray> Active live foreach cursors shared with nested source calls.
     */
    public array $liveArrays = [];
    /**
     * Cause of possible writes to shared roots not yet materialized in this path.
     */
    public ?string $unknownShared = null;

    /**
     * Allocates a distinct cell or object event within an execution path.
     * @param string $prefix Allocation category
     * @return string Fresh identity
     */
    public function fresh(string $prefix): string
    {
        return $prefix . ':' . $this->sequence++;
    }

    /**
     * Allocates a cell with an explicit initialization state.
     * @param Term $value Initial value
     * @return Location New cell address
     */
    public function allocate(Term $value): Location
    {
        $id = $this->fresh('cell');
        $this->cells[$id] = $value;
        $this->versions[$id] = 0;
        return new Location($id);
    }

    /**
     * Reads storage while following reference cells.
     * @param Location $location Address
     * @return Term Current value or an explicit uninitialized state
     */
    public function read(Location $location): Term
    {
        if ($location->unknown) {
            return Term::opaque('UNKNOWN_LOCATION');
        }
        $value = $this->dereference($this->cells[$location->root] ?? new Term('uninitialized'));
        foreach ($location->path as $key) {
            if ($value->kind !== 'array') {
                if ($value->kind === 'uninitialized') {
                    return $value;
                }
                $value = new Term('array-read', operands: [$value, Term::constant($key)]);
                continue;
            }
            $value = $this->element($value, $key);
        }
        return $value;
    }

    /**
     * Selects an array slot while retaining confidentiality of the container and key.
     * @param Term $array Array shape
     * @param int|string $key Normalized key
     * @param bool $secret Whether the evaluated key is confidential
     * @return Term Dereferenced entry or explicit absence
     */
    public function element(Term $array, int|string $key, bool $secret = false): Term
    {
        $value = $this->dereference($array->operands[$key] ?? (($array->attributes['open'] ?? false) === true ? Term::opaque('UNKNOWN_ARRAY_KEY') : new Term('uninitialized')));
        return ($array->secret || $secret) && !$value->secret ? new Term($value->kind, $value->literal, $value->operands, $value->attributes, true) : $value;
    }

    /**
     * Follows reference cells with cycle detection.
     * @param Term $value Stored value
     * @return Term Referenced value
     */
    public function dereference(Term $value): Term
    {
        $seen = [];
        $secret = $value->secret;
        while ($value->kind === 'cell' && is_string($value->literal)) {
            if (isset($seen[$value->literal])) {
                return new Term('opaque', 'CYCLIC_REFERENCE', attributes: ['type' => 'mixed', 'dependencyCoverage' => 'partial'], secret: $secret);
            }
            $seen[$value->literal] = true;
            $value = $this->cells[$value->literal] ?? new Term('uninitialized');
            $secret = $secret || $value->secret;
        }
        return $secret && !$value->secret ? new Term($value->kind, $value->literal, $value->operands, $value->attributes, true) : $value;
    }

    /**
     * Writes through an address, preserving unrelated keys and reference elements.
     * @param Location $location Destination
     * @param Term $value New value
     * @param bool $replacement Whether this operation replaces the entire selected array
     */
    public function write(Location $location, Term $value, bool $replacement = true): void
    {
        if ($location->unknown) {
            $this->cells[$location->root] = Term::opaque('UNKNOWN_WRITE', dependencies: [$value]);
        } else {
            $this->writePath($location->root, $location->path, $value, replacement: $replacement);
        }
        $this->versions[$location->root] = ($this->versions[$location->root] ?? 0) + 1;
    }

    /**
     * Updates a path within one storage root.
     * @param string $root Root cell identity
     * @param list<int|string> $path Ordered array keys
     * @param Term $value Assigned value
     * @param bool $rebind Whether to replace a final reference slot
     * @param bool $replacement Whether a root write represents whole-array assignment
     */
    public function writePath(string $root, array $path, Term $value, bool $rebind = false, bool $replacement = true): void
    {
        $before = $this->cells[$root] ?? new Term('uninitialized');
        if ($before->kind === 'cell' && is_string($before->literal)) {
            $this->writePath($before->literal, $path, $value, $rebind, $replacement);
            return;
        }
        $this->cells[$root] = $this->replace($before, $path, $value, $rebind, $replacement);
        $this->synchronize($root, $before, $this->cells[$root], $replacement && $path === []);
    }

    /**
     * Rebuilds only the affected part of a persistent array value.
     * @param Term $before Previous aggregate
     * @param list<int|string> $path Remaining path
     * @param Term $value Assigned value
     * @param bool $rebind Whether to replace a final reference slot
     * @param bool $replacement Whether a root write represents whole-array assignment
     * @return Term Updated aggregate
     */
    public function replace(Term $before, array $path, Term $value, bool $rebind = false, bool $replacement = true): Term
    {
        if ($path === [] && $rebind) {
            return $value;
        }
        if ($before->kind === 'cell' && is_string($before->literal)) {
            $this->writePath($before->literal, $path, $value, $rebind, $replacement);
            return $before;
        }
        if ($path === []) {
            return $value;
        }
        $key = array_shift($path);
        $entries = $before->kind === 'array' ? $before->operands : [];
        $entry = $this->replace($entries[$key] ?? new Term('uninitialized'), $path, $value, $rebind, $replacement);
        $base = $before->kind === 'array' ? $before : Term::array([], $before->kind !== 'uninitialized' && !($before->kind === 'constant' && $before->literal === null));
        return (new Arrays())->set($base, Term::constant($key), $entry);
    }

    /**
     * Materializes a reference cell only when an address escapes by reference.
     * @param Location $location Escaping address
     * @return string Shared cell identity
     */
    public function reference(Location $location): string
    {
        if (!$location->unknown && $this->read($location)->kind === 'uninitialized') {
            $this->write($location, Term::constant(null));
        }
        if ($location->path === []) {
            $root = $location->root;
            $seen = [];
            while (($this->cells[$root] ?? null)?->kind === 'cell' && is_string($this->cells[$root]->literal) && !isset($seen[$root])) {
                $seen[$root] = true;
                $root = $this->cells[$root]->literal;
            }
            return $root;
        }
        $raw = $this->raw($location);
        if ($raw->kind === 'cell' && is_string($raw->literal)) {
            $value = $this->cells[$raw->literal] ?? new Term('uninitialized');
            if ($this->read($location)->secret && !$value->secret) {
                $this->cells[$raw->literal] = new Term($value->kind, $value->literal, $value->operands, $value->attributes, true);
            }
            return $raw->literal;
        }
        $cell = $this->allocate($this->read($location));
        $this->write($location, new Term('cell', $cell->root));
        return $cell->root;
    }

    /**
     * Reads the final slot without dereferencing its contents.
     * @param Location $location Address
     * @return Term Raw stored slot
     */
    public function raw(Location $location): Term
    {
        $value = $this->cells[$location->root] ?? new Term('uninitialized');
        foreach ($location->path as $key) {
            $value = $this->dereference($value)->operands[$key] ?? new Term('uninitialized');
        }
        return $value;
    }

    /**
     * Removes an array entry without changing the referenced cell it held.
     * @param Location $location Removed address
     */
    public function remove(Location $location): void
    {
        if ($location->path === []) {
            unset($this->cells[$location->root]);
            return;
        }
        $path = $location->path;
        $key = array_pop($path);
        $parent = new Location($location->root, $path);
        $before = $this->read($parent);
        $entries = $before->operands;
        unset($entries[$key]);
        $this->write($parent, new Term('array', operands: $entries, attributes: [...$before->attributes, 'next' => (new Arrays())->next($before)], secret: $before->secret), replacement: false);
    }

    /**
     * Updates active by-reference cursors after one actual storage mutation.
     * @param string $root Mutated reference cell
     * @param Term $before Previous stored array
     * @param Term $after New stored array
     * @param bool $replacement Whether the entire referenced array was assigned
     */
    public function synchronize(string $root, Term $before, Term $after, bool $replacement): void
    {
        foreach ($this->liveArrays as $id => $cursor) {
            if ($cursor->location->root === $root) {
                $this->liveArrays[$id] = $cursor->changed($before, $after, $replacement);
            }
        }
    }

    /**
     * Dereferences array elements for public observations with a finite cycle boundary.
     * @param Term $value Observed value
     * @param int $depth Structural depth
     * @return Term Observable immutable value
     */
    public function materialize(Term $value, int $depth = 0): Term
    {
        return (new Materialization($this))->read($value, $depth);
    }
}
