<?php

declare(strict_types=1);

namespace Tests\Fake;

use Override;
use Requirements\Model\Source;
use Requirements\Source\SourceExtension;
use RuntimeException;

/**
 * A source extension whose document can never be read, as when a download fails.
 */
final class UnreadableSource implements SourceExtension
{
    /**
     * Fails to read the document, whatever the selector.
     *
     * @param Source $source The source declaration
     * @param string $selector The selector
     * @param string $directory The configuration directory
     * @param bool $live Whether to read live
     *
     * @return list<\Requirements\Source\Unit> Never returns
     *
     * @throws RuntimeException Always, naming the source URI
     */
    #[Override]
    public function select(Source $source, string $selector, string $directory, bool $live): array
    {
        throw new RuntimeException("Cannot read $source->uri.");
    }
}
