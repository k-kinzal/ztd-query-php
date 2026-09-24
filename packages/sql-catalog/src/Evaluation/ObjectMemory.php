<?php

declare(strict_types=1);

namespace SqlCatalog\Evaluation;

/**
 * Immutable object snapshots indexed by allocation, shared by aliases within one run.
 *
 * @visibility root
 */
final class ObjectMemory
{
    /**
     * @param array<string, Domain> $objects
     */
    public function __construct(private array $objects = [])
    {
    }

    /**
     * Stores a new snapshot without changing other runs.
     */
    public function remember(ObjectTerm $object): void
    {
        if ($object->identity !== null) {
            $this->objects[$object->identity] = Domain::of($object);
        }
    }

    /**
     * Stores alternative snapshots of each allocation as one domain.
     */
    public function rememberValue(Domain $value): void
    {
        $objects = [];
        foreach ($value->terms as $term) {
            if ($term instanceof ObjectTerm && $term->identity !== null) {
                $objects[$term->identity][] = $term;
            }
        }
        foreach ($objects as $identity => $terms) {
            $this->objects[$identity] = Domain::fromTerms($terms, $value->widened, $value->combined);
        }
    }

    /**
     * Registers an incoming object, preserving any later local snapshot.
     */
    public function import(Domain $value): void
    {
        foreach ($value->terms as $term) {
            if ($term instanceof ObjectTerm && $term->identity !== null && !isset($this->objects[$term->identity])) {
                $this->remember($term);
            }
            if ($term instanceof ArrayTerm) {
                foreach ($term->entries as $entry) {
                    $this->import($entry->value);
                }
            }
        }
    }

    /**
     * Resolves references, including references stored in arrays.
     */
    public function read(Domain $value): Domain
    {
        if ($this->objects === []) {
            return $value;
        }
        $terms = [];
        $widened = $value->widened;
        $combined = $value->combined;
        foreach ($value->terms as $term) {
            if ($term instanceof ObjectTerm && $term->identity !== null && isset($this->objects[$term->identity])) {
                $snapshot = $this->objects[$term->identity];
                $terms = array_merge($terms, $snapshot->terms);
                $widened = $widened || $snapshot->widened;
                $combined = $combined || $snapshot->combined;
            } elseif ($term instanceof ArrayTerm) {
                $terms[] = new ArrayTerm(array_map(fn (ArrayEntry $entry): ArrayEntry => new ArrayEntry($entry->key, $this->read($entry->value)), $term->entries), $term->complete);
            } else {
                $terms[] = $term;
            }
        }

        return Domain::fromTerms($terms, $widened, $combined);
    }

    /**
     * Copies the map; its domain and term values are immutable.
     */
    public function copy(): self
    {
        return new self($this->objects);
    }

    /**
     * Preserves both snapshots when runs must be joined.
     */
    public function join(self $other): self
    {
        $objects = $this->objects;
        foreach ($other->objects as $identity => $value) {
            $objects[$identity] = isset($objects[$identity]) ? $objects[$identity]->union($value) : $value;
        }

        return new self($objects);
    }

    /**
     * The snapshots that distinguish otherwise equal variable bindings.
     */
    public function signature(): string
    {
        $parts = [];
        foreach ($this->objects as $identity => $value) {
            $parts[] = $identity . '=' . $value->signature();
        }
        sort($parts);

        return implode(';', $parts);
    }
}
