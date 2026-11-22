<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Group;

use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Node;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;

/**
 * One `option = 'value'` of START GROUP_REPLICATION.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/start-group-replication.html.
 *
 * @visibility SqlSemantics
 * @example Holding a user option
 *     (new \SqlSemantics\Platform\MySql\Statement\Replication\Group\CredentialOption(\SqlSemantics\Platform\MySql\Statement\Replication\Group\Credential::User, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('rpl')))->value->value // => 'rpl'
 */
final class CredentialOption implements Node
{
    use Snapshot;

    /**
     * @param Credential $credential The option
     * @param Text $value The value
     */
    public function __construct(public readonly Credential $credential, public readonly Text $value)
    {
    }

    /**
     * Writes the option, `=` and the value.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->credential->value)->symbol('=')->node($this->value);
    }
}
