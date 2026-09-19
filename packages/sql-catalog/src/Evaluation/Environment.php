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

    /**
     * @param array<string, Domain> $variables Initial bindings, keyed by variable name without the sigil
     */
    public function __construct(array $variables = [])
    {
        $this->variables = $variables;
    }

    /**
     * The domain bound to a variable, or an unresolved domain when it has none.
     */
    public function read(string $name): Domain
    {
        return $this->variables[$name] ?? Domain::opaque(TypeShape::unknown(), Origin::Unresolved, '$' . $name);
    }

    /**
     * Binds a variable to a domain.
     */
    public function write(string $name, Domain $domain): void
    {
        $this->variables[$name] = $domain;
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
        return new self($this->variables);
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

        return new self($joined);
    }

    /**
     * Whether the two environments bind the same variables to the same domains.
     */
    public function equals(self $other): bool
    {
        if ($this->names() !== $other->names()) {
            return false;
        }
        foreach ($this->variables as $name => $domain) {
            if (!$domain->equals($other->read($name))) {
                return false;
            }
        }

        return true;
    }
}
