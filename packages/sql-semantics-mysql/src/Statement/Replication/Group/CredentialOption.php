<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Group;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;

/**
 * One `option = 'value'` of START GROUP_REPLICATION.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/start-group-replication.html.
 *
 * @visibility public
 * @example Rejecting a line feed in a group credential
 *     new \SqlSemantics\Platform\MySql\Statement\Replication\Group\CredentialOption(\SqlSemantics\Platform\MySql\Statement\Replication\Group\Credential::User, new \SqlSemantics\Platform\MySql\Statement\Literal\Text("a\nb")) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Holding a user option
 *     (new \SqlSemantics\Platform\MySql\Statement\Replication\Group\CredentialOption(\SqlSemantics\Platform\MySql\Statement\Replication\Group\Credential::User, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('rpl')))->value->value // => 'rpl'
 */
final class CredentialOption implements Node
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Credential $credential The option
     * @param Text $value The value
     */
    public function __construct(public readonly Credential $credential, public readonly Text $value, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        Check::input($value->radix === null, 'A group replication credential is a quoted string.');
        Check::input((new \SqlSemantics\Platform\MySql\Rules\Literal\LineText())->valid($value), 'A group replication credential cannot contain a line feed before NUL.');
    }

    /**
     * Writes the option, `=` and the value.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->credential->value)->symbol('=')->node($this->value);
    }
}
