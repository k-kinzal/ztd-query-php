<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Transaction;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;

/**
 * `ROLLBACK [WORK] [AND [NO] CHAIN] [[NO] RELEASE]`: a request to end the transaction and undo its changes.
 *
 * Mirrors SQLCOM_ROLLBACK with LEX::tx_chain and LEX::tx_release. Rule:
 * MYSQL-ROLLBACK-001. Each completion choice is true when written as AND CHAIN
 * or RELEASE, false when written with NO, and null when absent: an absent
 * choice takes the completion_type of the session, so an explicit NO is a
 * request of its own. AND CHAIN together with RELEASE is a syntax error of
 * the server and cannot be constructed. WORK is optional and not written.
 * The statement names no relation and has no facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/commit.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Rollback implements Statement
{
    use Snapshot;

    /**
     * @param bool|null $chain Whether AND CHAIN (true) or AND NO CHAIN (false) is written; null when absent
     * @param bool|null $release Whether RELEASE (true) or NO RELEASE (false) is written; null when absent
     * @throws InvalidConstruction When both AND CHAIN and RELEASE are requested
     */
    public function __construct(public readonly ?bool $chain = null, public readonly ?bool $release = null)
    {
        Check::input($chain !== true || $release !== true, 'AND CHAIN and RELEASE exclude each other.');
    }

    /**
     * Has nothing to derive: the request names no relation and no value.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ROLLBACK');
        if ($this->chain !== null) {
            $out->keyword(...($this->chain ? ['AND', 'CHAIN'] : ['AND', 'NO', 'CHAIN']));
        }
        if ($this->release !== null) {
            $out->keyword(...($this->release ? ['RELEASE'] : ['NO', 'RELEASE']));
        }
    }
}
