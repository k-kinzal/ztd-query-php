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

    /** @var array<string, Presence> */
    private array $presence = [];

    /**
     * Whether joining branch states lost their pairing.
     */
    public bool $combined = false;

    /**
     * @param array<string, Domain> $variables Initial bindings, keyed by variable name without the sigil
     */
    public function __construct(array $variables = [])
    {
        $this->variables = $variables;
        $this->presence = array_fill_keys(array_keys($variables), Presence::Present);
    }

    /**
     * The domain bound to a variable, or an unresolved domain when it has none.
     */
    public function read(string $name): Domain
    {
        if ($this->presence($name) === Presence::Absent) {
            return Domain::literal(null);
        }

        return $this->variables[$name] ?? Domain::opaque(TypeShape::unknown(), Origin::Unresolved, '$' . $name);
    }

    /**
     * Binds a variable to a domain.
     */
    public function write(string $name, Domain $domain): void
    {
        $this->variables[$name] = $domain;
        $this->presence[$name] = Presence::Present;
    }

    /**
     * Narrows a value while keeping a possible absence distinct from present null.
     */
    public function narrow(string $name, Domain $domain): void
    {
        $presence = $this->presence($name);
        $this->write($name, $domain);
        if ($presence !== Presence::Present && ($domain->type()->isNullable() || $domain->type()->isUnknown())) {
            $this->presence[$name] = $presence;
        }
    }

    /**
     * Whether the variable has a binding.
     */
    public function has(string $name): bool
    {
        return isset($this->presence[$name]);
    }

    /**
     * Drops the binding of a variable.
     */
    public function forget(string $name): void
    {
        unset($this->variables[$name], $this->presence[$name]);
    }

    /**
     * The bound variable names.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->presence);
    }

    /**
     * An independent copy, so a branch can be analyzed without disturbing the caller.
     */
    public function copy(): self
    {
        return clone $this;
    }

    /**
     * The environment holding, for each variable, what either branch may leave in it.
     *
     * A variable bound in only one branch keeps that branch's domain widened with
     * the unresolved value it holds when the branch is not taken.
     */
    public function join(self $other): self
    {
        $joined = new self();
        foreach (array_unique(array_merge($this->names(), $other->names())) as $name) {
            $left = $this->presence($name);
            $right = $other->presence($name);
            $joined->variables[$name] = $this->read($name)->union($other->read($name));
            $joined->presence[$name] = $left === $right ? $left : Presence::Maybe;
        }
        $joined->combined = $this->combined || $other->combined || !$this->equals($other);

        return $joined;
    }

    /**
     * The existence of a variable, independently of what value it holds.
     */
    public function presence(string $name): Presence
    {
        return $this->presence[$name] ?? Presence::Maybe;
    }

    /**
     * Records that a variable is definitely undefined.
     */
    public function markAbsent(string $name): void
    {
        unset($this->variables[$name]);
        $this->presence[$name] = Presence::Absent;
    }

    /**
     * Forgets both the value and existence after an unmodelled write.
     */
    public function invalidate(string $name, Origin $origin = Origin::Unresolved): void
    {
        $this->variables[$name] = Domain::opaque(TypeShape::unknown(), $origin, '$' . $name);
        $this->presence[$name] = Presence::Maybe;
    }

    /**
     * Replaces this state with the result of evaluating alternatives.
     */
    public function replace(self $other): void
    {
        $this->variables = $other->variables;
        $this->presence = $other->presence;
        $this->combined = $other->combined;
    }

    /**
     * A canonical string used to compare environments.
     */
    public function signature(): string
    {
        $parts = [];
        foreach ($this->presence as $name => $presence) {
            $domain = $this->read($name);
            $parts[] = ($presence === Presence::Present ? '' : $presence->value . ':') . $name . '=' . $domain->signature();
        }
        sort($parts);

        return ($this->combined ? 'combined;' : '') . implode(';', $parts);
    }

    /**
     * Whether the two environments bind the same names to the same values.
     */
    public function equals(self $other): bool
    {
        return $this->signature() === $other->signature();
    }
}
