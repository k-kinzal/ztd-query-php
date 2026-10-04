<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\NumberOptionKind;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * A table option whose value is a number, or DEFAULT for the options that accept it.
 *
 * The equals sign between the option and its value is optional and is not kept.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 *
 * @visibility public
 * @example Reading a table option
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT) AUTO_INCREMENT = 100');
 *     $create->statement->options[0] instanceof \SqlSemantics\Platform\MySql\Statement\Table\Option\NumberOption // => true
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
