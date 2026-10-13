<?php

declare(strict_types=1);

namespace Fuzz\Structure;

use ReflectionException;
use SqlSemantics\Diagnostic\InvalidConstruction;

/**
 * Samples public constructors, rejecting only their own declared invariants.
 */
final class Construction
{
    /**
     * The interpreter of PHP constructor types.
     */
    public readonly Inhabitants $types;

    /**
     * The constructor-type depth constraints, independent of execution.
     */
    public readonly Depth $depth;

    /**
     * @var array<string, int> Constructor rejection counts, including the invariant message
     */
    public array $rejections = [];

    /**
     * Constructor attempts consumed by this trial.
     */
    public int $attempts = 0;

    /**
     * @param Choices $choices The deterministic choices for this trial
     * @param Catalogue $catalogue The complete semantic value inventory
     * @param Metadata $metadata The shared PHP type metadata cache
     */
    public function __construct(public readonly Choices $choices, public readonly Catalogue $catalogue, public readonly Metadata $metadata)
    {
        $this->types = new Inhabitants($this);
        $this->depth = new Depth($catalogue, $metadata);
    }

    /**
     * @param class-string $class The concrete type or interface to inhabit
     * @throws Exhausted When the finite construction budget finds no inhabitant
     * @throws ReflectionException When a public constructor cannot be invoked
     */
    public function object(string $class, int $depth): object
    {
        if ($depth < 0 || $this->attempts >= 2000) {
            throw new Exhausted('The finite construction budget was exhausted at ' . $class);
        }
        if (enum_exists($class)) {
            return $this->choices->one($class::cases());
        }
        $implementations = array_values(array_filter(interface_exists($class) ? $this->catalogue->implementations($class) : [$class], fn (string $candidate): bool => $this->depth->object($candidate, $depth)));
        $deadline = min(2000, $this->attempts + 24 * 2 ** $depth);
        for ($attempt = 0; $attempt < 24 && $this->attempts < 2000; $attempt++) {
            if ($this->attempts >= $deadline) {
                throw new Exhausted('The subtree construction budget was exhausted at ' . $class);
            }
            $this->attempts++;
            $concrete = $this->choices->one($implementations);
            if (enum_exists($concrete)) {
                return $this->choices->one($concrete::cases());
            }
            [$reflection, $parameters] = $this->metadata->of($concrete);
            if (!$reflection->isInstantiable()) {
                throw new Exhausted('No public constructor for ' . $concrete);
            }
            try {
                $arguments = [];
                foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
                    $type = $parameters[$parameter->getName()] ?? null;
                    $fits = $type === null ? $this->depth->native($parameter->getType(), $concrete, $depth) : $this->depth->type($type, $concrete, $depth);
                    if ($parameter->isDefaultValueAvailable() && (!$fits || $this->choices->integer(0, 3) !== 0)) {
                        $arguments[] = $parameter->getDefaultValue();
                        continue;
                    }
                    $arguments[] = $type === null ? $this->types->native($parameter->getType(), $concrete, $depth) : $this->types->generate($type, $concrete, $depth);
                }

                return $reflection->newInstanceArgs($arguments);
            } catch (InvalidConstruction $failure) {
                $key = $concrete . ': ' . $failure->getMessage();
                $this->rejections[$key] = ($this->rejections[$key] ?? 0) + 1;
            } catch (Exhausted) {
                // Try another combination within the same finite budget.
            }
        }

        throw new Exhausted('No constructor-accepted combination for ' . $class);
    }
}
