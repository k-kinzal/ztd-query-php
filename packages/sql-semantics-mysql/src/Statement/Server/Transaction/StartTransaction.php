<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Transaction;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rendering\TransactionSql;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Source\Span;

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
 *     [$start->toString(), $start->characteristics[1]] // => ['START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY', \SqlSemantics\Platform\MySql\Statement\Server\Transaction\TransactionCharacteristic::ReadOnly]
 */
final class StartTransaction extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements TransactionStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;
    use TransactionSql;

    /**
     * @var list<TransactionCharacteristic> The characteristics in written order
     */
    public readonly array $characteristics;

    /**
     * @param list<TransactionCharacteristic> $characteristics The characteristics in written order
     * @throws InvalidConstruction When READ ONLY and READ WRITE are both requested
     * @param LanguageProfile $profile The selected grammar and lexical interpretation; MySQL 8.4.7 by default
     * @param Charset|null $client The client encoding used to reconstruct identifiers
     * @param Span|null $source The optional original statement location
     */
    public function __construct(array $characteristics = [], public readonly LanguageProfile $profile = new LanguageProfile(GrammarRelease::MySql847), public readonly ?Charset $client = null, public readonly ?Span $source = null, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?Span $inputSource = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        Check::input($profile->grammar->database() === 'mysql', 'A MySQL transaction statement uses a MySQL language profile.');
        $this->characteristics = Check::listOf($characteristics, TransactionCharacteristic::class, 'START TRANSACTION takes a list of characteristics.');
        Check::input(
            !in_array(TransactionCharacteristic::ReadOnly, $this->characteristics, true) || !in_array(TransactionCharacteristic::ReadWrite, $this->characteristics, true),
            'READ ONLY and READ WRITE exclude each other.',
        );
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
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
