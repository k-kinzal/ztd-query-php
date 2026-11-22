<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Key\Option;

use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;

/**
 * The ENGINE_ATTRIBUTE or SECONDARY_ENGINE_ATTRIBUTE of an index (MySQL 8.0.21 and later).
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-index.html.
 *
 * @visibility SqlSemantics
 */
final class IndexEngineAttribute implements IndexOption
{
    use Snapshot;

    /**
     * @param Text $attribute The attribute document
     * @param bool $secondary Whether it is the SECONDARY_ENGINE_ATTRIBUTE
     */
    public function __construct(public readonly Text $attribute, public readonly bool $secondary = false)
    {
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->secondary ? 'SECONDARY_ENGINE_ATTRIBUTE' : 'ENGINE_ATTRIBUTE')->node($this->attribute);
    }
}
