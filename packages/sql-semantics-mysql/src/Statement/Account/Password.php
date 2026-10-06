<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account;

use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The password SET PASSWORD assigns: a string, or in MySQL 5.x a string inside PASSWORD() or OLD_PASSWORD().
 *
 * The string is an operand with its exact value; it is never masked. Its
 * meaning depends on the release (PasswordFunction).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-password.html.
 *
 * @visibility public
 * @example Holding a password written with PASSWORD()
 *     $password = new \SqlSemantics\Platform\MySql\Statement\Account\Password(new \SqlSemantics\Platform\MySql\Statement\Literal\Text('pw'), \SqlSemantics\Platform\MySql\Statement\Account\PasswordFunction::Password);
 *     [$password->text->value, $password->function?->value] // => ['pw', 'PASSWORD']
 */
final class Password implements Node
{
    use Snapshot;

    /**
     * @param Text $text The password or hash
     * @param PasswordFunction|null $function The function written around it, when one is
     */
    public function __construct(public readonly Text $text, public readonly ?PasswordFunction $function = null)
    {
    }

    /**
     * Writes the string, inside its function when one is written.
     */
    public function render(Output $out): void
    {
        if ($this->function === null) {
            $out->node($this->text);

            return;
        }
        $out->keyword($this->function->value)->glue()->symbol('(')->node($this->text)->symbol(')');
    }
}
