<?php

declare(strict_types=1);

namespace SqlCatalog\Evaluation;

use SqlCatalog\Text\Origin;
use SqlCatalog\Type\TypeShape;

/**
 * What the analyzer knows about each variable at one point in a function body.
 *
 * @visibility root
 */
final class Environment
{
    /**
     * @var array<string, Domain>
     */
    private array $variables;

    private ?ObjectMemory $objects;

    /**
     * @param array<string, Domain> $variables Initial bindings, keyed by variable name without the sigil
     */
    public function __construct(array $variables = [], ?ObjectMemory $objects = null)
    {
        $this->variables = $variables;
        $this->objects = $objects;
        foreach ($variables as $value) {
            if ($value->soleObject()?->identity !== null) {
                $this->objects()->import($value);
            }
        }
    }

    /**
     * The domain bound to a variable, or an unresolved domain when it has none.
     */
    public function read(string $name): Domain
    {
        $value = $this->variables[$name] ?? Domain::opaque(TypeShape::unknown(), Origin::Unresolved, '$' . $name);

        return $this->refresh($value);
    }

    /**
     * Resolves an already-evaluated object reference after subsequent argument effects.
     */
    public function refresh(Domain $value): Domain
    {
        return $this->objects?->read($value) ?? $value;
    }

    /**
     * Binds a variable to a domain.
     */
    public function write(string $name, Domain $domain): void
    {
        $this->variables[$name] = $domain;
        $this->objects?->import($domain);
        $object = $domain->soleObject();
        if ($object?->identity !== null) {
            $this->objects()->remember($object);
        }
    }

    /**
     * Whether the variable has a binding.
     */
    public function has(string $name): bool
    {
        return isset($this->variables[$name]);
    }

    /**
     * Drops the binding of a variable.
     */
    public function forget(string $name): void
    {
        unset($this->variables[$name]);
    }

    /**
     * The bound variable names.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->variables);
    }

    /**
     * An independent copy, so a branch can be analyzed without disturbing the caller.
     */
    public function copy(): self
    {
        return new self($this->variables, $this->objects?->copy());
    }

    /**
     * The environment holding, for each variable, what either branch may leave in it.
     *
     * A variable bound in only one branch keeps that branch's domain widened with
     * the unresolved value it holds when the branch is not taken.
     */
    public function join(self $other): self
    {
        $joined = [];
        foreach ($this->variables as $name => $domain) {
            $joined[$name] = $other->has($name) ? $domain->union($other->read($name)) : $domain;
        }
        foreach ($other->variables as $name => $domain) {
            if (!isset($joined[$name])) {
                $joined[$name] = $domain;
            }
        }

        $objects = $this->objects === null ? $other->objects?->copy() : ($other->objects === null ? $this->objects->copy() : $this->objects->join($other->objects));

        return new self($joined, $objects);
    }

    /**
     * Replaces this run with the alternatives left by independent branches.
     */
    public function mergeBranches(self $left, self $right): void
    {
        $joined = $left->join($right);
        $this->variables = $joined->variables;
        $this->objects = $joined->objects;
    }

    /**
     * A canonical string used to compare environments.
     */
    public function signature(): string
    {
        $parts = [];
        foreach ($this->variables as $name => $domain) {
            $parts[] = $name . '=' . $domain->signature();
        }
        sort($parts);

        $objects = $this->objects?->signature() ?? '';

        return implode(';', $parts) . ($objects === '' ? '' : ';objects:' . $objects);
    }

    /**
     * Whether the two environments bind the same names to the same values.
     */
    public function equals(self $other): bool
    {
        if ($this->names() !== $other->names()) {
            return false;
        }
        foreach ($this->variables as $name => $domain) {
            if (!$this->read($name)->equals($other->read($name))) {
                return false;
            }
        }

        return true;
    }

    /**
     * The object snapshots shared by this run's aliases.
     */
    public function objects(): ObjectMemory
    {
        return $this->objects ??= new ObjectMemory();
    }
}
