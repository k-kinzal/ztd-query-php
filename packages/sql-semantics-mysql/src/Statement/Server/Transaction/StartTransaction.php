<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Transaction;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `START TRANSACTION [characteristic, …]`: a request to start a transaction.
 *
 * Mirrors SQLCOM_BEGIN with LEX::start_transaction_opt. Rule:
 * MYSQL-START-TRANSACTION-001. The characteristics are kept in written
 * order; the server merges them into a set, so a repeated one has no further
 * effect. READ ONLY and READ WRITE together are a syntax error of the server
 * and cannot be constructed. Without an access mode the transaction takes the
 * session's access mode. The statement names no relation and has no facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/commit.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Starting a read-only transaction with a consistent snapshot
 *     $start = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('start transaction with consistent snapshot, read only');
 *     [$start->toString(), $start->statement->characteristics[1]] // => ['START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY', \SqlSemantics\Platform\MySql\Statement\Server\Transaction\TransactionCharacteristic::ReadOnly]
 */
final class StartTransaction implements Statement
{
    use Snapshot;

    /**
     * @var list<TransactionCharacteristic> The characteristics in written order
     */
    public readonly array $characteristics;

    /**
     * @param list<TransactionCharacteristic> $characteristics The characteristics in written order
     * @throws InvalidConstruction When READ ONLY and READ WRITE are both requested
     */
    public function __construct(array $characteristics = [])
    {
        $this->characteristics = Check::listOf($characteristics, TransactionCharacteristic::class, 'START TRANSACTION takes a list of characteristics.');
        Check::input(
            !in_array(TransactionCharacteristic::ReadOnly, $this->characteristics, true) || !in_array(TransactionCharacteristic::ReadWrite, $this->characteristics, true),
            'READ ONLY and READ WRITE exclude each other.',
        );
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
        $out->keyword('START', 'TRANSACTION');
        foreach ($this->characteristics as $position => $characteristic) {
            if ($position > 0) {
                $out->symbol(',');
            }
            $out->keyword(...explode(' ', $characteristic->value));
        }
    }
}
