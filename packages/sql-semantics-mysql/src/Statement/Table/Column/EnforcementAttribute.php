<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Column;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * An ENFORCED or NOT ENFORCED attribute of a column, which applies to the CHECK constraint written before it.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table-check-constraints.html.
 *
 * @visibility SqlSemantics
 */
final class EnforcementAttribute implements ColumnAttribute
{
    use Snapshot;

    /**
     * @param bool $enforced Whether ENFORCED is written without NOT
     */
    public function __construct(public readonly bool $enforced)
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
        if (!$this->enforced) {
            $out->keyword('NOT');
        }
        $out->keyword('ENFORCED');
    }
}
