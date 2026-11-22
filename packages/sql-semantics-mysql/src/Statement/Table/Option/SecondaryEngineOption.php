<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option;

use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;

/**
 * The SECONDARY_ENGINE option (MySQL 8.0.13 and later): the secondary storage engine, or NULL for none.
 *
 * The equals sign between the option and its value is optional and is not kept.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 *
 * @visibility SqlSemantics
 */
final class SecondaryEngineOption implements TableOption
{
    use Snapshot;

    /**
     * @param Name|null $engine The secondary engine; null for NULL
     */
    public function __construct(public readonly ?Name $engine)
    {
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('SECONDARY_ENGINE');
        if ($this->engine === null) {
            $out->keyword('NULL');
        } else {
            $out->name($this->engine, NameUse::Label);
        }
    }
}
