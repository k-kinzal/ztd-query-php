<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\NumberOptionKind;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;
use SqlSemantics\Rendering\Output;

/**
 * A table option whose value is a number, or DEFAULT for the options that accept it.
 *
 * The optional equals sign is retained independently of the option value.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 *
 * @visibility public
 * @example Reading a table option
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT) AUTO_INCREMENT = 100');
 *     $create->options[0] instanceof \SqlSemantics\Platform\MySql\Statement\Table\Option\NumberOption // => true
 */
final class NumberOption implements TableOption
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param NumberOptionKind $kind The option
     * @param Numeral|null $value The number; null for DEFAULT
     * @param bool $equals Whether the optional equals sign is written
     */
    public function __construct(public readonly NumberOptionKind $kind, public readonly ?Numeral $value, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly bool $equals = false)
    {
        Check::input($value !== null || $kind->defaultable(), 'Only PACK_KEYS and the STATS_ options accept DEFAULT.');
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->kind->value);
        if ($this->equals) {
            $out->symbol('=');
        }
        if ($this->value === null) {
            $out->keyword('DEFAULT');
        }
        $out->node($this->value);
    }
}
