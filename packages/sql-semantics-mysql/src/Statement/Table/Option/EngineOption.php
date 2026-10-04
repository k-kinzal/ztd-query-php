<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * The ENGINE option: the storage engine of the table.
 *
 * The equals sign between the option and its value is optional and is not kept.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 *
 * @visibility public
 * @example Reading a table option
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT) ENGINE = InnoDB');
 *     $create->statement->options[0] instanceof \SqlSemantics\Platform\MySql\Statement\Table\Option\EngineOption // => true
 */
final class EngineOption implements TableOption
{
    use Snapshot;

    /**
     * @param Name $engine The storage engine
     */
    public function __construct(public readonly Name $engine)
    {
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('ENGINE')->name($this->engine, NameUse::Label);
    }
}
