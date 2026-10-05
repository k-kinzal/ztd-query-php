<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Server\Magnitudes;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The identifier of an XA transaction: `gtrid [, bqual [, formatID]]`.
 *
 * Mirrors XID. The global transaction identifier and the branch qualifier
 * are strings of at most 64 bytes each, written as quoted strings or as
 * hexadecimal or bit literals; the format identifier is an unsigned number
 * and defaults to 1. A longer part is a syntax error of the server and
 * cannot be constructed; a format identifier above 2^63-1 is a syntax error
 * from MySQL 5.7 on and is refused when a statement holding it is derived
 * for such a release (MYSQL-SERVER-MAGNITUDE-001).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/xa-statements.html.
 *
 * @visibility public
 * @example Holding the three parts of an identifier
 *     $xid = new \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\Xid(new \SqlSemantics\Platform\MySql\Statement\Literal\Text('g'), new \SqlSemantics\Platform\MySql\Statement\Literal\Text('b'), new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('7'));
 *     [$xid->transaction->value, $xid->branch?->value, $xid->format?->text] // => ['g', 'b', '7']
 */
final class Xid implements Node
{
    use Snapshot;

    /**
     * @param Text $transaction The global transaction identifier
     * @param Text|null $branch The branch qualifier, when written
     * @param Numeral|null $format The format identifier, when written; it needs a branch qualifier
     * @throws InvalidConstruction When a part is too long or the format is written without a branch qualifier
     */
    public function __construct(public readonly Text $transaction, public readonly ?Text $branch = null, public readonly ?Numeral $format = null)
    {
        $magnitudes = new Magnitudes();
        Check::input($magnitudes->bytes($transaction) <= 64, 'A global transaction identifier holds at most 64 bytes.');
        Check::input($branch === null || $magnitudes->bytes($branch) <= 64, 'A branch qualifier holds at most 64 bytes.');
        Check::input($format === null || $branch !== null, 'A format identifier follows a branch qualifier.');
    }

    /**
     * Writes the parts separated by commas.
     */
    public function render(Output $out): void
    {
        $out->node($this->transaction);
        if ($this->branch !== null) {
            $out->symbol(',')->node($this->branch);
        }
        if ($this->format !== null) {
            $out->symbol(',')->node($this->format);
        }
    }
}
