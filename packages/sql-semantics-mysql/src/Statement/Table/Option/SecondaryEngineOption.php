<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

/**
 * The SECONDARY_ENGINE option (MySQL 8.0.13 and later): the secondary storage engine, or NULL for none.
 *
 * The optional assignment sign is retained independently of the option value.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 *
 * @visibility public
 * @example Reading a table option
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT) SECONDARY_ENGINE = NULL');
 *     $create->options[0] instanceof \SqlSemantics\Platform\MySql\Statement\Table\Option\SecondaryEngineOption // => true
 */
final class SecondaryEngineOption implements TableOption
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Name|null $engine The secondary engine; null for NULL
     * @param \SqlSemantics\Platform\MySql\Statement\AssignmentOperator|null $operator The written assignment sign, or null when omitted
     */
    public function __construct(public readonly ?Name $engine, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly ?\SqlSemantics\Platform\MySql\Statement\AssignmentOperator $operator = null)
    {
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('SECONDARY_ENGINE');
        if ($this->operator !== null) {
            $out->symbol($this->operator->value);
        }
        if ($this->engine === null) {
            $out->keyword('NULL');
        } else {
            $out->name($this->engine, NameUse::Label);
        }
    }
}
