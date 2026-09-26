<?php

declare(strict_types=1);

namespace Tests\Fake;

use Override;
use Requirements\Model\Source;
use Requirements\Source\SourceExtension;
use Requirements\Source\Unit;

/**
 * A source extension whose document is one message held in memory.
 */
final class MemorySource implements SourceExtension
{
    /**
     * Selects the single message unit, whatever the selector.
     *
     * @param Source $source The source declaration
     * @param string $selector The selector
     * @param string $directory The configuration directory
     * @param bool $live Whether to read live
     *
     * @return list<Unit> The message:1 unit
     */
    #[Override]
    public function select(Source $source, string $selector, string $directory, bool $live): array
    {
        return [new Unit('message:1', 'A service message.')];
    }
}
