<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option;

use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;
use SqlSemantics\Rendering\Output;

/**
 * The default collation of the table: [DEFAULT] COLLATE.
 *
 * The optional assignment sign is retained independently of the option value.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 *
 * @visibility public
 * @example Reading a table option
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT) COLLATE = utf8mb4_bin');
 *     $create->options[0] instanceof \SqlSemantics\Platform\MySql\Statement\Table\Option\CollationOption // => true
 */
final class CollationOption implements TableOption
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param CollationName $collation The collation, or DEFAULT
     * @param \SqlSemantics\Platform\MySql\Statement\AssignmentOperator|null $operator The written assignment sign, or null when omitted
     * @param bool $defaultKeyword Whether the optional DEFAULT keyword is written
     */
    public function __construct(public readonly CollationName $collation, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly ?\SqlSemantics\Platform\MySql\Statement\AssignmentOperator $operator = null, public readonly bool $defaultKeyword = false)
    {
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        if ($this->defaultKeyword) {
            $out->keyword('DEFAULT');
        }
        $out->keyword('COLLATE');
        if ($this->operator !== null) {
            $out->symbol($this->operator->value);
        }
        $out->node($this->collation);
    }
}
