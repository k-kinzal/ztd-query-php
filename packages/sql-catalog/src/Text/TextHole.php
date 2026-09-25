<?php

declare(strict_types=1);

namespace SqlCatalog\Text;

use Override;
use SqlCatalog\Type\TypeShape;

/**
 * A gap where a value the analyzer could not resolve is spliced into a string.
 *
 * @visibility root
 */
final class TextHole implements TextSegment
{
    /**
     * @param Origin $origin Where the unresolved value comes from
     * @param TypeShape $type The static type of the spliced value
     * @param string|null $expression The source expression, when it is short enough to quote
     */
    public function __construct(
        public readonly Origin $origin,
        public readonly TypeShape $type,
        public readonly ?string $expression = null,
    ) {
    }

    /**
     * The marker standing in for the unresolved value.
     */
    #[Override]
    public function display(): string
    {
        return '{$}';
    }
}
