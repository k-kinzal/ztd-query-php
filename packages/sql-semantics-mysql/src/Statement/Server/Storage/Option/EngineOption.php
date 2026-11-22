<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Storage\Option;

use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * `[STORAGE] ENGINE [=] name`: the storage engine of a tablespace, undo tablespace or log file group.
 *
 * STORAGE and the equals sign are optional and not written. The server resolves the name against the engines it has.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-tablespace.html.
 *
 * @visibility SqlSemantics
 * @example Holding the option
 *     (new \SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\EngineOption(new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name('InnoDB')))->engine->value // => 'InnoDB'
 */
final class EngineOption implements StorageOption
{
    use Snapshot;

    /**
     * @param Name $engine The engine name
     */
    public function __construct(public readonly Name $engine)
    {
    }

    /**
     * Answers the keyword of the option.
     */
    public function keyword(): string
    {
        return 'ENGINE';
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('ENGINE')->name($this->engine, NameUse::Identifier);
    }
}
