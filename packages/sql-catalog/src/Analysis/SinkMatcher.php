<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis;

use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Extension\SinkCallKind;
use SqlCatalog\Extension\SinkSpec;
use SqlCatalog\Php\ProgramIndex;

/**
 * Decides whether a call in the source is one of the database calls to catalog.
 *
 * @visibility root
 */
final class SinkMatcher
{
    /**
     * @var list<SinkSpec>
     */
    private array $sinks;

    private ProgramIndex $index;

    /**
     * @param list<SinkSpec> $sinks The calls the enabled extensions recognise
     * @param ProgramIndex $index The declarations of the analyzed source, for subclass checks
     */
    public function __construct(array $sinks, ProgramIndex $index)
    {
        $this->sinks = $sinks;
        $this->index = $index;
    }

    /**
     * The call matching a method written on a receiver of the given domain.
     */
    public function matchMethod(Domain $receiver, string $method): ?SinkSpec
    {
        foreach ($this->byName(SinkCallKind::Method, $method) as $sink) {
            if ($sink->receiverType !== null && $this->receiverMatches($receiver, $sink->receiverType)) {
                return $sink;
            }
        }

        return null;
    }

    /**
     * The call matching a static method written on the named class.
     */
    public function matchStatic(string $className, string $method): ?SinkSpec
    {
        foreach ($this->byName(SinkCallKind::StaticCall, $method) as $sink) {
            if ($sink->receiverType !== null && $this->classMatches($className, $sink->receiverType)) {
                return $sink;
            }
        }

        return null;
    }

    /**
     * The call matching a free function of that name.
     */
    public function matchFunction(string $name): ?SinkSpec
    {
        $matches = $this->byName(SinkCallKind::FunctionCall, $name);

        return $matches === [] ? null : $matches[0];
    }

    /**
     * The calls written with that name, whatever they are written on.
     *
     * @return list<SinkSpec>
     */
    public function byName(SinkCallKind $callKind, string $name): array
    {
        $matches = [];
        foreach ($this->sinks as $sink) {
            if ($sink->callKind === $callKind && $sink->matchesName($name)) {
                $matches[] = $sink;
            }
        }

        return $matches;
    }

    /**
     * Whether an extension already models what the receiver is.
     *
     * An extension that names a class's calls is the model of that class.
     * Walking into that class's own implementation from every call site
     * re-derives the statements it issues at the sites they are already read
     * from, once per caller, which costs a great deal and adds nothing that
     * reading the class itself does not give.
     */
    public function models(Domain $receiver): bool
    {
        foreach ($this->sinks as $sink) {
            if ($sink->receiverType !== null && $this->receiverMatches($receiver, $sink->receiverType)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a receiver of the given domain can be of the expected class.
     */
    public function receiverMatches(Domain $receiver, string $expected): bool
    {
        foreach ($receiver->type()->classNames() as $className) {
            if ($this->classMatches($className, $expected)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a class is, or inherits from, the expected one.
     *
     * Inheritance is read from the analyzed source first, so a drop-in
     * replacement declared in the project is recognised, and from the running
     * process second, which is what resolves the built-in driver classes.
     */
    public function classMatches(string $className, string $expected): bool
    {
        $left = ltrim($className, '\\');
        $right = ltrim($expected, '\\');
        if (strcasecmp($left, $right) === 0) {
            return true;
        }
        if ($this->index->isInstanceOf($left, $right)) {
            return true;
        }

        return class_exists($left, false) && is_a($left, $right, true);
    }
}
