<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option;

use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\TextOptionKind;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;
use SqlSemantics\Rendering\Output;

/**
 * A table option whose value is a string, such as COMMENT, CONNECTION or DATA DIRECTORY.
 *
 * The optional equals sign is retained independently of the option value.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 *
 * @visibility public
 * @example Reading a table option
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT) COMMENT = \'x\'');
 *     $create->options[0] instanceof \SqlSemantics\Platform\MySql\Statement\Table\Option\TextOption // => true
 */
final class TextOption implements TableOption
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param TextOptionKind $kind The option
     * @param Text $value The string
     * @param bool $equals Whether the optional equals sign is written
     */
    public function __construct(public readonly TextOptionKind $kind, public readonly Text $value, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly bool $equals = false)
    {
        if (in_array($kind, [TextOptionKind::EngineAttribute, TextOptionKind::SecondaryEngineAttribute], true)) {
            \SqlSemantics\Platform\MySql\Rules\Text\Json\Attribute::check($value);
        }
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword(...explode(' ', $this->kind->value));
        if ($this->equals) {
            $out->symbol('=');
        }
        $out->node($this->value);
    }
}
