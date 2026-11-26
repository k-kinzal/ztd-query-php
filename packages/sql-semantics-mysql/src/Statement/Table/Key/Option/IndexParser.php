<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Key\Option;

use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * The WITH PARSER option of a full-text index: the full-text parser plugin.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-index.html.
 *
 * @visibility SqlSemantics
 */
final class IndexParser implements IndexOption
{
    use Snapshot;

    /**
     * @param Name $parser The parser plugin
     */
    public function __construct(public readonly Name $parser)
    {
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('WITH', 'PARSER')->name($this->parser, NameUse::Label);
    }
}
