<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\View;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Ordering\AliasHeading;
use SqlSemantics\Platform\MySql\Ir\Ordering\RequestedResultAlias;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeLocalNames;

/**
 * Establishes the heading that can be emitted for the pinned view profile.
 * @visibility SqlSemantics\Platform\MySql
 */
final class ViewCatalogHeading
{
    /**
     * Resolves a written column list to its native output name and diagnostics.
     */
    public function explicit(string $written, GrammarRelease $release): ViewColumn
    {
        if (!$this->legacy($release)) {
            (new ViewProjectionAliases())->check($written);

            return new ViewColumn(new Identifier($written));
        }
        $effective = (new AliasHeading())->normalize($written);

        return new ViewColumn($written === $effective ? new Identifier($effective) : new RequestedResultAlias($written));
    }

    /**
     * Checks the heading against the chosen declaration syntax and result aliases.
     * @param non-empty-list<ViewColumn> $columns The exact ordered result heading
     */
    public function check(array $columns, Select $query): void
    {
        $legacy = $this->legacy($query->release);
        $aliases = new ViewProjectionAliases();
        $native = new NativeLocalNames();
        $implicit = $this->usesImplicit($columns, $query->release);
        foreach ($columns as $position => $column) {
            $name = $column->name;
            if ($legacy) {
                Check::input(
                    $name instanceof RequestedResultAlias || $native->prefix($name->value) === $name->value,
                    'A legacy view heading needs a native effective name or its requested alias.'
                );
                continue;
            }
            Check::input($name instanceof Identifier, 'A modern view heading uses an exact column name.');
            if (!$implicit) {
                $aliases->check($name->value);
                continue;
            }
            $body = $query->columns[$position]->name;
            if ($body instanceof RequestedResultAlias) {
                Check::input($body->value === $name->value, 'An empty modern view heading must preserve each requested body name.');
                continue;
            }
            $aliases->check($name->value);
            Check::input($native->prefix($name->value) === $name->value, 'An inherited view heading needs an effective body name.');
        }
    }

    /**
     * Selects the native inferred form when a modern heading contains an empty name.
     * @param list<ViewColumn> $columns The ordered result heading
     */
    public function usesImplicit(array $columns, GrammarRelease $release): bool
    {
        if (!$this->legacy($release)) {
            foreach ($columns as $column) {
                if ($column->name->value === '') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Distinguishes the releases that assign explicit headings at execution.
     */
    public function legacy(GrammarRelease $release): bool
    {
        return $release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744;
    }
}
