<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\NumberOptionKind;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;

/**
 * A table option whose value is a number, or DEFAULT for the options that accept it.
 *
 * The equals sign between the option and its value is optional and is not kept.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 *
 * @visibility SqlSemantics
 */
final class NumberOption implements TableOption
{
    use Snapshot;

    /**
     * @param NumberOptionKind $kind The option
     * @param Numeral|null $value The number; null for DEFAULT
     */
    public function __construct(public readonly NumberOptionKind $kind, public readonly ?Numeral $value)
    {
        Check::input($value !== null || $kind->defaultable(), 'Only PACK_KEYS and the STATS_ options accept DEFAULT.');
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->kind->value);
        if ($this->value === null) {
            $out->keyword('DEFAULT');
        }
        $out->node($this->value);
    }
}
