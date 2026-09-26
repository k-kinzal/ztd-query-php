<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Text;

use Override;
use SqlCatalog\Core\Type\TypeShape;

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
     * @param string|null $variable The PHP variable read at the use site, when known
     */
    public function __construct(
        public readonly Origin $origin,
        public readonly TypeShape $type,
        public readonly ?string $expression = null,
        public readonly ?string $variable = null,
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
