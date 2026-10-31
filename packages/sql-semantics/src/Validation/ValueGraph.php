<?php

declare(strict_types=1);

namespace SqlSemantics\Validation;

use ReflectionClass;
use ReflectionProperty;
use ReflectionReference;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Snapshot;
use UnitEnum;

/**
 * Checks that SQL operands belong to the semantic value domain.
 *
 * Every reachable object must be an enum case or an instance of a final
 * class of the admitted namespaces that uses the Snapshot trait and whose
 * public operands are initialized readonly properties. Private binding state is completed once by the occurrence APIs. Properties hold null, booleans, integers, strings,
 * arrays without references, or such objects. Floats, closures, resources and
 * classes of any other origin are rejected, whatever interface they implement.
 *
 * @visibility SqlSemantics
 */
final class ValueGraph
{
    /**
     * @var array<class-string, list<ReflectionProperty>>
     */
    private array $properties = [];

    /**
     * @param list<string> $namespaces The namespace prefixes of the closed value domain
     */
    public function __construct(private readonly array $namespaces)
    {
    }

    /**
     * Answers every object reachable from a root, the root included, after auditing each.
     *
     * @param bool $reuse Retained for callers; binding state requires a fresh traversal
     * @param bool $bindings Include private binding edges when auditing the complete retained graph
     * @return list<object>
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When a reachable value is outside the closed immutable domain
     */
    public function objects(object $root, bool $reuse = false, bool $complete = true, bool $bindings = true): array
    {
        $objects = [];
        $seen = [];
        $pending = [$root];
        while ($pending !== []) {
            $value = array_pop($pending);
            if (is_array($value)) {
                foreach ($value as $key => $item) {
                    Check::input(ReflectionReference::fromArrayElement($value, $key) === null, 'A semantic value holds no PHP reference.');
                    $pending[] = $item;
                }
                continue;
            }
            if (!is_object($value)) {
                Check::input($value === null || is_bool($value) || is_int($value) || is_string($value), 'A semantic value holds null, booleans, integers, strings, lists and closed values only.');
                continue;
            }
            if (isset($seen[spl_object_id($value)])) {
                continue;
            }
            $seen[spl_object_id($value)] = true;
            $objects[] = $value;
            foreach ($this->members($value) as $property) {
                if (!$bindings && $property->isPrivate()) {
                    continue;
                }
                if (!$complete && !$property->isInitialized($value)) {
                    continue;
                }
                Check::input($property->isInitialized($value), 'A semantic value has no uninitialised property.');
                $pending[] = $property->getValue($value);
            }
        }

        return $objects;
    }

    /**
     * Answers the properties to follow for an object after checking its class is admitted.
     *
     * @return list<ReflectionProperty>
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the class is outside the closed immutable domain
     */
    public function members(object $value): array
    {
        $class = $value::class;
        if (isset($this->properties[$class])) {
            return $this->properties[$class];
        }
        $admitted = false;
        foreach ($this->namespaces as $namespace) {
            $admitted = $admitted || str_starts_with($class, $namespace);
        }
        Check::input($admitted, 'A value of class ' . $class . ' is not part of the closed semantic value domain.');
        if ($value instanceof UnitEnum) {
            return $this->properties[$class] = [];
        }
        $reflection = new ReflectionClass($value);
        Check::input($reflection->isFinal(), 'Semantic value class ' . $class . ' must be final.');
        $properties = [];
        $traits = $reflection->getTraitNames();
        do {
            foreach ($reflection->getProperties() as $property) {
                if ($property->getDeclaringClass()->getName() !== $reflection->getName()) {
                    continue;
                }
                Check::input(($property->isReadOnly() || $property->isPrivate()) && !$property->isStatic(), 'Semantic value class ' . $class . ' exposes readonly operands only.');
                $properties[] = $property;
            }
            $reflection = $reflection->getParentClass();
        } while ($reflection !== false);
        Check::input(in_array(Snapshot::class, $traits, true) || in_array(\SqlSemantics\Statement\Semantics\Occurrence::class, $traits, true), 'Semantic value class ' . $class . ' must close cloning, dynamic properties and unserialization with the Snapshot trait.');

        return $this->properties[$class] = $properties;
    }
}
