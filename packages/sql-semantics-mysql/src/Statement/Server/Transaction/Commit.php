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
 * `COMMIT [WORK] [AND [NO] CHAIN] [[NO] RELEASE]`: a request to end the transaction and make its changes permanent.
 *
 * Mirrors SQLCOM_COMMIT with LEX::tx_chain and LEX::tx_release. Rule:
 * MYSQL-COMMIT-001. Each completion choice is true when written as AND CHAIN
 * or RELEASE, false when written with NO, and null when absent: an absent
 * choice takes the completion_type of the session, so an explicit NO is a
 * request of its own. AND CHAIN together with RELEASE is a syntax error of
 * the server and cannot be constructed. WORK is optional and retained as a spelling choice.
 * The statement names no relation and has no facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/commit.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Ending a transaction with explicit completion choices
 *     $end = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('commit work and no chain release');
 *     [$end->toString(), $end->chain, $end->release] // => ['COMMIT WORK AND NO CHAIN RELEASE', false, true]
 */
final class Commit implements TransactionStatement
{
    use Snapshot;
    use TransactionSql;

    /**
     * @param bool|null $chain Whether AND CHAIN (true) or AND NO CHAIN (false) is written; null when absent
     * @param bool|null $release Whether RELEASE (true) or NO RELEASE (false) is written; null when absent
     * @param bool $work Whether the optional WORK keyword is written
     * @throws InvalidConstruction When both AND CHAIN and RELEASE are requested
     * @param LanguageProfile $profile The selected grammar and lexical interpretation; MySQL 8.4.7 by default
     * @param Charset|null $client The client encoding used to reconstruct identifiers
     * @param Span|null $source The optional original statement location
     */
    public function __construct(public readonly ?bool $chain = null, public readonly ?bool $release = null, public readonly bool $work = false, public readonly LanguageProfile $profile = new LanguageProfile(GrammarRelease::MySql847), public readonly ?Charset $client = null, public readonly ?Span $source = null)
    {
        Check::input($profile->grammar->database() === 'mysql', 'A MySQL transaction statement uses a MySQL language profile.');
        Check::input($chain !== true || $release !== true, 'AND CHAIN and RELEASE exclude each other.');
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
        $out->keyword('COMMIT');
        if ($this->work) {
            $out->keyword('WORK');
        }
        if ($this->chain !== null) {
            $out->keyword(...($this->chain ? ['AND', 'CHAIN'] : ['AND', 'NO', 'CHAIN']));
        }
        if ($this->release !== null) {
            $out->keyword(...($this->release ? ['RELEASE'] : ['NO', 'RELEASE']));
        }
    }
}
