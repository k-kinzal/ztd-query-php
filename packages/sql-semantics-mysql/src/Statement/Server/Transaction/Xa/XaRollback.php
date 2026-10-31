<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rendering\TransactionSql;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\TransactionStatement;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Source\Span;

/**
 * `XA ROLLBACK xid`: a request to roll back an XA transaction.
 *
 * Mirrors Sql_cmd_xa_rollback. Rule: MYSQL-XA-ROLLBACK-001. The statement names no
 * relation and has no facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/xa-statements.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the request
 *     $xa = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("xa rollback X'0f', 'b', 3");
 *     [$xa->toString(), $xa->xid->format?->text === '3'] // => ["XA ROLLBACK X'0f', 'b', 3", true]
 * @example Retaining a format identifier outside the selected release
 *     (new \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaRollback(new \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\Xid(new \SqlSemantics\Platform\MySql\Statement\Literal\Text('a'), new \SqlSemantics\Platform\MySql\Statement\Literal\Text('b'), new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('9223372036854775808'))))->xid->problem(\SqlSemantics\Contract\GrammarRelease::MySql847) instanceof \SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedXaIdentifier // => true
 */
final class XaRollback extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements TransactionStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;
    use TransactionSql;

    /**
     * @param Xid $xid The transaction identifier
     * @param LanguageProfile $profile The selected grammar and lexical interpretation; MySQL 8.4.7 by default
     * @param Charset|null $client The client encoding used to reconstruct identifiers
     * @param Span|null $source The optional original statement location
     */
    public function __construct(public readonly Xid $xid, public readonly LanguageProfile $profile = new LanguageProfile(GrammarRelease::MySql847), public readonly ?Charset $client = null, public readonly ?Span $source = null, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?Span $inputSource = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        Check::input($profile->grammar->database() === 'mysql', 'A MySQL transaction statement uses a MySQL language profile.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Records the identifier condition for execution in the selected release.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $this->xid->derive($derivation);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('XA', 'ROLLBACK')->node($this->xid);
    }
}
