<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\ForeignServer;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One option of CREATE SERVER or ALTER SERVER: `USER 'name'`, `PORT 3306`, ….
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-server.html.
 *
 * @visibility public
 * @example Holding the port of a server
 *     $option = new \SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\ServerOption(\SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\ServerOptionKind::Port, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('3307'));
 *     $option->value instanceof \SqlSemantics\Platform\MySql\Statement\Literal\Numeral // => true
 */
final class ServerOption implements Node
{
    use Snapshot;

    /**
     * @param ServerOptionKind $kind The option
     * @param Text|Numeral $value A number for PORT, a string for the other options
     * @throws InvalidConstruction When the value does not fit the option
     */
    public function __construct(public readonly ServerOptionKind $kind, public readonly Text|Numeral $value)
    {
        Check::input(($kind === ServerOptionKind::Port) === ($value instanceof Numeral), 'PORT takes a number and the other server options a string.');
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->kind->value)->node($this->value);
    }
}
