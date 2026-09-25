<?php

declare(strict_types=1);

namespace Tests\Support;

use Requirements\Model\Source;
use Requirements\Source\SourceExtension;
use Requirements\Source\Unit;

final class MemorySource implements SourceExtension
{
    public function select(Source $source, string $selector, string $directory, bool $live): array
    {
        return [new Unit('message:1', 'A service message.')];
    }
}
