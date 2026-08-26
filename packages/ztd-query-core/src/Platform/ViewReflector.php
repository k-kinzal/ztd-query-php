<?php

declare(strict_types=1);

namespace ZtdQuery\Platform;

use ZtdQuery\Schema\ViewDefinition;

/**
 * Reads the views a database has, and what each of them selects from.
 *
 * A view is a statement standing in for a table, so ZTD has to know both what
 * it selects and which tables that reaches, in order to shadow it.
 */
interface ViewReflector
{
    /**
     * @return array<string, ViewDefinition>
     */
    public function reflectViews(): array;
}
