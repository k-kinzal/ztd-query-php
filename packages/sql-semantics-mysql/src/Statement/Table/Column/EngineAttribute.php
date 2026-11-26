<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Column;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;

/**
 * The ENGINE_ATTRIBUTE or SECONDARY_ENGINE_ATTRIBUTE of a column: a JSON document passed to the storage engine (MySQL 8.0.21 and later).
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html.
 *
 * @visibility SqlSemantics
 */
final class EngineAttribute implements ColumnAttribute
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
     * Derives nothing: the attribute holds no expression.
     */
    public function deriveAttribute(Derivation $derivation, Environment $scope): void
    {
    }

    /**
     * Writes the attribute.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->secondary ? 'SECONDARY_ENGINE_ATTRIBUTE' : 'ENGINE_ATTRIBUTE')->node($this->attribute);
    }
}
