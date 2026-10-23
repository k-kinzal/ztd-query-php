<?php

declare(strict_types=1);

namespace Tests\Generator;

use SqlSemantics\Diagnostic\InvalidConstruction;

/**
 * Samples public constructors, rejecting only their own declared invariants.
 *
 * After a refusal, retains the completed dependencies and resamples one argument
 * at a time for the same concrete type and depth. Cross-field constraints can
 * then be solved without repeatedly rebuilding every unrelated child. No accepted
 * parent is discarded, and every trial still shares the same attempt cap.
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
     * The selected root can use the complete shared 10,000-attempt cap. Nested
     * constructors retain smaller depth-dependent bounds, including their descendants.
     *
     * @param class-string $class The concrete type or interface to inhabit
     * @throws Exhausted When the finite construction budget finds no inhabitant
     */
    public function object(string $class, int $depth): object
    {
        if ($depth < 0 || $this->attempts >= 10000) {
            throw new Exhausted('The finite construction budget was exhausted at ' . $class);
        }
        if (enum_exists($class)) {
            return $this->choices->one($class::cases());
        }
        $implementations = interface_exists($class) ? $this->catalogue->implementations($class) : [$class];
        $depths = array_values(array_filter(range(0, $depth), fn (int $remaining): bool => $this->depth->object($class, $remaining)));
        $deadline = $this->attempts === 0 ? 10000 : min(10000, $this->attempts + 24 * 2 ** $depth);
        /** @var array<string, Arguments> $samples */
        $samples = [];
        while ($this->attempts < $deadline) {
            $this->attempts++;
            $remaining = interface_exists($class) || !$this->depth->object($class, 0) ? $this->choices->one($depths) : $depth;
            $concrete = $this->choices->one(array_values(array_filter($implementations, fn (string $candidate): bool => $this->depth->object($candidate, $remaining))));
            if (enum_exists($concrete)) {
                return $this->choices->one($concrete::cases());
            }
            [$declaration, $parameters] = $this->metadata->of($concrete);
            if ($declaration->isAbstract() || ($declaration->getMethod('__construct') !== null && $declaration->getMethod('__construct')->isPrivate())) {
                throw new Exhausted('No public constructor for ' . $concrete);
            }
            $sampleKey = $concrete . ':' . $remaining;
            $slots = $this->metadata->parameters($declaration);
            $sample = $samples[$sampleKey] ?? new Arguments([]);
            /** A stagnant invariant switches to complete resampling until another constraint is reached. */
            $arguments = $sample->repeats >= max(1, count($slots) * 2) ? [] : $sample->values;
            $failureKey = '';
            $repeats = 0;
            /** Visit each argument under a stagnant invariant; random slot selection can miss its constrained operand. */
            $replace = $arguments === [] || $slots === [] ? -1 : ($sample->repeats - 1) % count($slots);
            try {
                foreach ($slots as $index => $parameter) {
                    if ($index !== $replace && array_key_exists($index, $arguments)) {
                        continue;
                    }
                    $type = $parameters[$this->metadata->parameter($parameter)] ?? null;
                    $fits = $type === null ? $this->depth->native($parameter->type, $concrete, $remaining) : $this->depth->type($type, $concrete, $remaining);
                    if ($parameter->default !== null && (!$fits || $this->choices->integer(0, 3) !== 0)) {
                        $arguments[$index] = (new Defaults())->value($parameter->default);
                        continue;
                    }
                    $arguments[$index] = $type === null ? $this->types->native($parameter->type, $concrete, $remaining) : $this->types->generate($type, $concrete, $remaining);
                }

                return new $concrete(...$arguments);
            } catch (InvalidConstruction $failure) {
                $key = $concrete . ': ' . $failure->getMessage();
                $this->rejections[$key] = ($this->rejections[$key] ?? 0) + 1;
                // A diagnostic may name sampled operands; only its invariant site identifies progress.
                $site = $failure->getTrace()[0] ?? [];
                $failureKey = $concrete . ':' . ($site['file'] ?? $failure->getFile()) . ':' . ($site['line'] ?? $failure->getLine());
                $repeats = $sample->failure === $failureKey ? $sample->repeats + 1 : 1;
            } catch (Exhausted) {
                // Keep completed dependencies while trying another argument combination.
            }
            $samples[$sampleKey] = new Arguments($arguments, $failureKey, $repeats);
        }

        throw new Exhausted('No constructor-accepted combination for ' . $class);
    }
}
