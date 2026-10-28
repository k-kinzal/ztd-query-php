<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

/**
 * The ENGINE option: the storage engine of the table.
 *
 * The optional equals sign is retained independently of the option value.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 *
 * @visibility public
 * @example Reading a table option
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT) ENGINE = InnoDB');
 *     $create->options[0] instanceof \SqlSemantics\Platform\MySql\Statement\Table\Option\EngineOption // => true
 */
final class EngineOption implements TableOption
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Name $engine The storage engine
     * @param bool $equals Whether the optional equals sign is written
     */
    public function __construct(public readonly Name $engine, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly bool $equals = false)
    {
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('ENGINE');
        if ($this->equals) {
            $out->symbol('=');
        }
        $out->name($this->engine, NameUse::Label);
    }
}
