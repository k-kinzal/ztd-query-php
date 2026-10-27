<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Transaction;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rendering\TransactionSql;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Source\Span;

/**
 * `BEGIN [WORK]`: a request to start a transaction without characteristics.
 *
 * Mirrors SQLCOM_BEGIN with no start options. Rule: MYSQL-BEGIN-001. BEGIN
 * is the alias of START TRANSACTION that takes no characteristics; inside a
 * stored program the word starts a compound statement instead, which is a
 * different production. WORK is optional and retained as a spelling choice. The statement
 * names no relation and has no facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/commit.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Starting a transaction with BEGIN WORK
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('BEGIN WORK')->toString() // => 'BEGIN WORK'
 */
final class Begin extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements TransactionStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;
    use TransactionSql;

    /**
     * @param bool $work Whether the optional WORK keyword is written
     * @param LanguageProfile $profile The selected grammar and lexical interpretation; MySQL 8.4.7 by default
     * @param Charset|null $client The client encoding used to reconstruct identifiers
     * @param Span|null $source The optional original statement location
     */
    public function __construct(public readonly bool $work = false, public readonly LanguageProfile $profile = new LanguageProfile(GrammarRelease::MySql847), public readonly ?Charset $client = null, public readonly ?Span $source = null, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?Span $inputSource = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        Check::input($profile->grammar->database() === 'mysql', 'A MySQL transaction statement uses a MySQL language profile.');
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
        $out->keyword('BEGIN');
        if ($this->work) {
            $out->keyword('WORK');
        }
    }
}
