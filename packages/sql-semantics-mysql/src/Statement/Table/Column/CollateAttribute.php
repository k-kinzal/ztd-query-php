<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Column;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;

/**
 * The COLLATE attribute of a character column written among the other attributes.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/charset-column.html.
 *
 * @visibility SqlSemantics
 */
final class CollateAttribute implements ColumnAttribute
{
    use Snapshot;

    /**
     * @param CollationName $collation The collation
     */
    public function __construct(public readonly CollationName $collation)
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
        $out->keyword('COLLATE')->node($this->collation);
    }
}
