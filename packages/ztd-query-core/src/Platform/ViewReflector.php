<?php

declare(strict_types=1);

namespace ZtdQuery\Platform;

use ZtdQuery\Schema\ViewDefinition;

interface ViewReflector
{
    /** @return array<string, ViewDefinition> */
    public function reflectViews(): array;
}
