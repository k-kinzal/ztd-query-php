<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Text;

use Override;

/**
 * A run of characters the analyzer resolved exactly.
 *
 * @visibility root
 */
final class LiteralText implements TextSegment
{
    /**
     * @param string $text The resolved characters
     */
    public function __construct(public readonly string $text)
    {
    }

    /**
     * The characters themselves.
     */
    #[Override]
    public function display(): string
    {
        return $this->text;
    }
}
