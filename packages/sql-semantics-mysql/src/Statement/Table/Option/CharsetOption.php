<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option;

use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use SqlSemantics\Rendering\Output;

/**
 * The default character set of the table: [DEFAULT] CHARACTER SET or CHARSET.
 *
 * The optional equals sign is retained independently of the option value.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 *
 * @visibility public
 * @example Reading a table option
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT) CHARSET = utf8mb4');
 *     $create->options[0] instanceof \SqlSemantics\Platform\MySql\Statement\Table\Option\CharsetOption // => true
 */
final class CharsetOption implements TableOption
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param CharsetName $charset The character set, or DEFAULT
     * @param bool $equals Whether the optional equals sign is written
     * @param bool $defaultKeyword Whether the optional DEFAULT keyword is written
     * @param CharsetForm $form The keyword introducing the character set
     */
    public function __construct(public readonly CharsetName $charset, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly bool $equals = false, public readonly bool $defaultKeyword = false, public readonly CharsetForm $form = CharsetForm::Named)
    {
        \SqlSemantics\Diagnostic\Check::input($form->named(), 'A character set option uses CHARSET, CHARACTER SET or CHAR SET.');
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        if ($this->defaultKeyword) {
            $out->keyword('DEFAULT');
        }
        $out->keyword(...explode(' ', $this->form->value));
        if ($this->equals) {
            $out->symbol('=');
        }
        $out->node($this->charset);
    }
}
