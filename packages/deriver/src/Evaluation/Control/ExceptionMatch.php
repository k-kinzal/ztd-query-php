<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Control;

use Deriver\ControlFlow\Program;
use Deriver\Evaluation\Call\Creation\Builtins;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Value\Term;

/**
 * Partitions throwable bounds in catch order without inventing a runtime class.
 * @visibility root
 */
final class ExceptionMatch
{
    /**
     * @param Program $program Captured declaration hierarchy
     */
    public function __construct(public readonly Program $program)
    {
    }

    /**
     * Separates matching and remaining values, preserving prior catch exclusions.
     * @param Term $value Thrown value or type refinement
     * @param list<string> $types Ordered catch union
     * @return array{?Term, ?Term} Matching subset and uncaught remainder
     */
    public function partition(Term $value, array $types): array
    {
        if (in_array($value->kind, ['constant', 'array', 'closure', 'enum'], true)) {
            return [null, $value];
        }
        $exact = $this->exact($value);
        if ($exact !== null) {
            foreach ($types as $type) {
                if ((new Dispatch($this->program))->subtype($exact, $type)) {
                    return [$value, null];
                }
            }
            return [null, $value];
        }
        $types = $this->arms(implode('|', $types));
        $bound = $value->attributes['type'] ?? $value->attributes['class'] ?? ($value->kind === 'throwable' ? $value->literal : 'mixed');
        $arms = $this->arms(is_string($bound) ? $bound : 'mixed');
        $excluded = explode('|', (string) ($value->attributes['excluded-types'] ?? ''));
        $matched = [];
        $remaining = [];
        foreach ($arms as $arm) {
            $covered = false;
            foreach ($types as $type) {
                $intersection = $this->intersection($arm, $type);
                if ($intersection !== null && !$this->excluded($intersection, $excluded)) {
                    $matched[] = $intersection;
                    $covered = $covered || $this->implies($arm, $type);
                }
            }
            if (!$covered) {
                $remaining[] = $arm;
            }
        }
        if ($matched === []) {
            return [null, $value];
        }
        if ($remaining === []) {
            return [$value, null];
        }
        return [$this->refine($value, $matched, $excluded), $this->refine($value, $remaining, [...$excluded, ...$types])];
    }

    /**
     * Identifies concrete throwable and object runtime classes.
     * @param Term $value Value being thrown
     * @return string|null Exact class, or null for an upper bound
     */
    public function exact(Term $value): ?string
    {
        if (($value->attributes['uncertain'] ?? false) === true) {
            return null;
        }
        if ($value->kind === 'throwable') {
            return is_string($value->literal) ? $value->literal : null;
        }
        $class = $value->attributes['class'] ?? null;
        return is_string($class) && $this->concreteClass($class) ? $class : null;
    }

    /**
     * Expands the two legal throwable roots and parses PHP union bounds.
     * @param string $bound Union or intersection expression
     * @return list<string> Disjoint or conservatively overlapping union arms
     */
    public function arms(string $bound): array
    {
        $arms = [];
        foreach (explode('|', $bound) as $arm) {
            if (strcasecmp($arm, 'Throwable') === 0) {
                array_push($arms, 'Exception', 'Error');
            } else {
                $arms[] = trim($arm, '()');
            }
        }
        return $arms;
    }

    /**
     * Refines one conjunction with a catch class when overlap is feasible.
     * @param string $arm Existing conjunction
     * @param string $type Catch class
     * @return string|null Refined conjunction, or a disjoint pair
     */
    public function intersection(string $arm, string $type): ?string
    {
        if ($this->implies($arm, $type)) {
            return $arm;
        }
        $parts = [];
        $dispatch = new Dispatch($this->program);
        foreach (explode('&', $arm) as $part) {
            if (!$this->overlaps($part, $type)) {
                return null;
            }
            if (!$dispatch->subtype($type, $part) && !in_array($part, ['mixed', 'object'], true)) {
                $parts[] = $part;
            }
        }
        $parts[] = $type;
        return implode('&', array_unique($parts));
    }

    /**
     * Tests whether any conjunct establishes the requested supertype.
     * @param string $arm Intersection bound
     * @param string $type Requested supertype
     * @return bool Whether all values satisfy the supertype
     */
    public function implies(string $arm, string $type): bool
    {
        foreach (explode('&', $arm) as $part) {
            if ((new Dispatch($this->program))->subtype($part, $type)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Rejects incompatible class roots while retaining possible interface overlap.
     * @param string $left Existing class or primitive bound
     * @param string $right Catch class
     * @return bool Whether a common runtime subtype may exist
     */
    public function overlaps(string $left, string $right): bool
    {
        if (in_array($left, ['int', 'float', 'string', 'bool', 'true', 'false', 'null', 'array', 'resource', 'never', 'void'], true)) {
            return false;
        }
        if (in_array($left, ['mixed', 'object'], true)) {
            return true;
        }
        $dispatch = new Dispatch($this->program);
        if ($dispatch->subtype($left, $right) || $dispatch->subtype($right, $left)) {
            return true;
        }
        return !$this->concreteClass($left) || !$this->concreteClass($right);
    }

    /**
     * Distinguishes known class roots from interfaces and unavailable declarations.
     * @param string $name Class or interface spelling
     * @return bool Whether PHP single inheritance rules out unrelated class roots
     */
    public function concreteClass(string $name): bool
    {
        $seen = [];
        while (!isset($seen[strtolower($name)])) {
            $seen[strtolower($name)] = true;
            $declaration = $this->program->classes()[strtolower($name)] ?? null;
            if ($declaration === null) {
                return strcasecmp($name, 'Closure') === 0 || (new Builtins())->name($name) !== null && strcasecmp($name, 'Throwable') !== 0;
            }
            if ($declaration->interface) {
                return false;
            }
            if ($declaration->parent === '') {
                return true;
            }
            $name = $declaration->parent;
        }
        return false;
    }

    /**
     * Rejects a subset already consumed by an earlier catch clause.
     * @param string $arm Candidate intersection
     * @param list<string> $types Previously caught types
     * @return bool Whether this subset has already been removed
     */
    public function excluded(string $arm, array $types): bool
    {
        foreach ($types as $type) {
            if ($type !== '' && $this->implies($arm, $type)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Carries a narrowed bound and catch exclusions without changing the input identity.
     * @param Term $value Original expression
     * @param list<string> $arms Feasible union arms
     * @param list<string> $excluded Removed subtype sets
     * @return Term Symbolic subset retaining its source operand
     */
    public function refine(Term $value, array $arms, array $excluded): Term
    {
        return new Term('type-refinement', 'catch', [$value], ['type' => implode('|', array_unique($arms)), 'excluded-types' => implode('|', array_unique(array_filter($excluded, static fn (string $type): bool => $type !== '')))]);
    }
}
