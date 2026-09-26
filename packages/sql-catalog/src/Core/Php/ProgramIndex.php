<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Php;

use PhpParser\Node\Expr;

/**
 * Every declaration the analyzed source tree contains, looked up by name.
 *
 * Resolution follows parents, traits and interfaces, so a repository method
 * inherited from an abstract base is found from the subclass the call names.
 *
 * @visibility root
 */
final class ProgramIndex
{
    /**
     * @param array<string, ClassShape> $classes Class-likes, keyed by lower-case fully qualified name
     * @param array<string, FunctionShape> $functions Functions, keyed by lower-case fully qualified name
     * @param array<string, Expr> $constants Global constant expressions, keyed by fully qualified name
     */
    public function __construct(
        public readonly array $classes = [],
        public readonly array $functions = [],
        public readonly array $constants = [],
    ) {
    }

    /**
     * The class-like of that name, or null when the source tree does not declare it.
     */
    public function findClass(?string $name): ?ClassShape
    {
        return $name === null ? null : ($this->classes[strtolower(ltrim($name, '\\'))] ?? null);
    }

    /**
     * The function of that name, or null when the source tree does not declare it.
     */
    public function findFunction(string $name): ?FunctionShape
    {
        return $this->functions[strtolower(ltrim($name, '\\'))] ?? null;
    }

    /**
     * The global constant expression of that name, or null when there is none.
     */
    public function findConstant(string $name): ?Expr
    {
        return $this->constants[ltrim($name, '\\')] ?? null;
    }

    /**
     * The method of that name on the class or anything it inherits from.
     */
    public function findMethod(?string $className, string $method): ?MethodShape
    {
        $key = strtolower($method);
        foreach ($this->lineage($className) as $shape) {
            if (isset($shape->methods[$key])) {
                return $shape->methods[$key];
            }
        }

        return null;
    }

    /**
     * The class constant expression of that name on the class or anything it inherits from.
     */
    public function findClassConstant(?string $className, string $constant): ?Expr
    {
        foreach ($this->lineage($className) as $shape) {
            if (isset($shape->constants[$constant])) {
                return $shape->constants[$constant];
            }
        }

        return null;
    }

    /**
     * The declared type of a property on the class or anything it inherits from.
     */
    public function findPropertyType(?string $className, string $property): ?\SqlCatalog\Core\Type\TypeShape
    {
        foreach ($this->lineage($className) as $shape) {
            if (isset($shape->propertyTypes[$property])) {
                return $shape->propertyTypes[$property];
            }
        }

        return null;
    }

    /**
     * Whether the class is, or inherits from, the named one.
     *
     * The walk follows written names rather than indexed declarations, so a
     * class that extends one the analyzed source does not declare — a driver
     * class, or a framework base class — is still recognised as inheriting it.
     */
    public function isInstanceOf(?string $className, string $expected): bool
    {
        $target = strtolower(ltrim($expected, '\\'));
        $queue = $className === null ? [] : [$className];
        $seen = [];

        while ($queue !== []) {
            $current = strtolower(ltrim(array_shift($queue), '\\'));
            if ($current === $target) {
                return true;
            }
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;
            foreach ($this->findClass($current)?->ancestors() ?? [] as $ancestor) {
                $queue[] = $ancestor;
            }
        }

        return false;
    }

    /**
     * The class and everything it inherits from, nearest first, without repeats.
     *
     * @return list<ClassShape>
     */
    public function lineage(?string $className): array
    {
        $shape = $this->findClass($className);
        if ($shape === null) {
            return [];
        }

        $seen = [strtolower($shape->name) => true];
        $queue = [$shape];
        $lineage = [];
        while ($queue !== []) {
            $current = array_shift($queue);
            $lineage[] = $current;
            foreach ($current->ancestors() as $ancestor) {
                $key = strtolower(ltrim($ancestor, '\\'));
                $next = $this->findClass($ancestor);
                if ($next === null || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $queue[] = $next;
            }
        }

        return $lineage;
    }

    /**
     * The concrete implementations of a method, for a call the declaration cannot answer.
     *
     * A call on an abstract class or an interface names a method whose body is
     * somewhere else. Collecting the bodies the source tree does declare is what
     * lets a statement built by a subclass resolve instead of becoming a gap.
     *
     * @param int $limit How many implementations to return before giving up on enumerating them
     * @return list<MethodShape>
     */
    public function implementationsOf(?string $className, string $method, int $limit = 8): array
    {
        if ($className === null) {
            return [];
        }
        $key = strtolower($method);
        $found = [];
        foreach ($this->classes as $shape) {
            $declared = $shape->methods[$key] ?? null;
            if ($declared?->node?->getStmts() === null) {
                continue;
            }
            if ($this->isInstanceOf($shape->name, $className)) {
                $found[] = $declared;
            }
            if (count($found) > $limit) {
                return [];
            }
        }

        return $found;
    }

    /**
     * The index holding the declarations of both, with this one taking precedence.
     */
    public function merge(self $other): self
    {
        return new self(
            $this->classes + $other->classes,
            $this->functions + $other->functions,
            $this->constants + $other->constants,
        );
    }
}
