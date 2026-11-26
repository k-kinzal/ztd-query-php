<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Validation;

use ReflectionClass;
use ReflectionProperty;
use ReflectionReference;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use UnitEnum;

/**
 * Audits that a value graph is deeply immutable and made of the closed value domain only.
 *
 * Every reachable object must be an enum case or an instance of a final
 * class of the admitted namespaces that uses the Snapshot trait and whose
 * properties are all initialised readonly properties. Properties hold null, booleans, integers, strings,
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
     * Answers reachable objects, including the root, after auditing each value.
     * @param array<int, string> $discarded Already audited edges to omit from the binding walk
     *
     * @return list<object>
     * @throws \SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction When a reachable value is outside the closed immutable domain
     */
    public function objects(object $root, array $discarded = []): array
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
            $identity = spl_object_id($value);
            if (isset($seen[$identity])) {
                continue;
            }
            $seen[$identity] = true;
            $objects[] = $value;
            foreach ($this->members($value) as $property) {
                Check::input($property->isInitialized($value), 'A semantic value has no uninitialised property.');
                if ($property->getName() === ($discarded[$identity] ?? null)) {
                    continue;
                }
                $pending[] = $property->getValue($value);
            }
        }

        return $objects;
    }

    /**
     * Answers the properties to follow for an object after checking its class is admitted.
     *
     * @return list<ReflectionProperty>
     * @throws \SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction When the class is outside the closed immutable domain
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
        foreach ($reflection->getProperties() as $property) {
            Check::input($property->isReadOnly() && !$property->isStatic(), 'Semantic value class ' . $class . ' must have readonly properties only.');
            $properties[] = $property;
        }
        Check::input(in_array(Snapshot::class, $reflection->getTraitNames(), true), 'Semantic value class ' . $class . ' must close cloning, dynamic properties and unserialization with the Snapshot trait.');

        return $this->properties[$class] = $properties;
    }
}
