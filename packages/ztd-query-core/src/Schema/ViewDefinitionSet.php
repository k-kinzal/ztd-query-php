<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

/**
 * The views a database has, in an order they can be created in.
 *
 * A view may select from another, so recreating them in the shadow means
 * having the ones a view depends on before it.
 */
final class ViewDefinitionSet
{
    /** @var array<string, ViewDefinition> */
    private array $definitions = [];

    public function register(string $viewName, ViewDefinition $definition): void
    {
        $this->definitions[$viewName] = $definition;
    }

    public function has(string $viewName): bool
    {
        return isset($this->definitions[$viewName]);
    }

    public function hasAnyViews(): bool
    {
        return $this->definitions !== [];
    }

    /**
     * @return array<string, ViewDefinition>
     */
    public function orderedDefinitions(): array
    {
        $remaining = $this->definitions;
        $ordered = [];
        while ($remaining !== []) {
            $progress = false;
            foreach ($remaining as $viewName => $definition) {
                $pendingDependencies = array_filter(
                    $definition->dependencies,
                    static fn (string $dependency): bool => isset($remaining[$dependency]),
                );
                if ($pendingDependencies !== []) {
                    continue;
                }
                $ordered[$viewName] = $definition;
                unset($remaining[$viewName]);
                $progress = true;
            }
            if (!$progress) {
                foreach ($remaining as $viewName => $definition) {
                    $ordered[$viewName] = $definition;
                }
                break;
            }
        }

        return $ordered;
    }
}
