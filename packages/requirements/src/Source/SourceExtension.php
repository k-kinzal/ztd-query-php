<?php

declare(strict_types=1);

namespace Requirements\Source;

use Requirements\Model\Source;

interface SourceExtension
{
    /** @return list<Unit> */
    public function select(Source $source, string $selector, string $directory, bool $live): array;
}
