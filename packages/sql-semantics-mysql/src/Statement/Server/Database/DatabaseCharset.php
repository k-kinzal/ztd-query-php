<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Database;

use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use SqlSemantics\Rendering\Output;

/**
 * `[DEFAULT] CHARACTER SET [=] charset`: the default character set of the database's tables.
 *
 * DEFAULT, the equals sign, and the CHARSET, CHAR SET or CHARACTER SET
 * keyword are retained as explicit choices.
 * The name DEFAULT, accepted by MySQL 5.x, takes the server's character
 * set.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-database.html.
 *
 * @visibility public
 * @example Reading the character set
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE DATABASE d DEFAULT CHARACTER SET = latin1');
 *     [$create->toString(), $create->options[0]->charset->name?->value] // => ['CREATE DATABASE d DEFAULT CHARACTER SET = latin1', 'latin1']
 */
final class DatabaseCharset implements DatabaseOption
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param CharsetName $charset The character set
     * @param bool $equals Whether the optional equals sign is written
     * @param bool $defaultKeyword Whether the optional DEFAULT keyword is written
     * @param CharsetForm $form The keyword introducing the character set
     */
    public function __construct(public readonly CharsetName $charset, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly bool $equals = false, public readonly bool $defaultKeyword = false, public readonly CharsetForm $form = CharsetForm::CharacterSet)
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
