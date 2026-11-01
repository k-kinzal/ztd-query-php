<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rendering\TransactionSql;
use SqlSemantics\Platform\MySql\Rules\Server\AdminRows;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\TransactionStatement;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Source\Span;

/**
 * `XA RECOVER [CONVERT XID]`: a request to list the prepared XA transactions.
 *
 * Mirrors Sql_cmd_xa_recover. Rule: MYSQL-XA-RECOVER-001. Its rows are those of MYSQL-ADMIN-ROWS-001. The statement names no
 * relation.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/xa-statements.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the request
 *     $xa = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("xa recover convert xid");
 *     [$xa->toString(), $xa->convertXid] // => ["XA RECOVER CONVERT XID", true]
 */
final class XaRecover extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements TransactionStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;
    use TransactionSql;

    /**
     * The output slots owned by this row-producing statement.
     */
    public readonly \SqlSemantics\Statement\Shape\RowShape $shape;

    /**
     * @param bool $convertXid Whether CONVERT XID is written: the identifiers are listed in hexadecimal (MySQL 5.7 and later)
     * @param LanguageProfile $profile The selected grammar and lexical interpretation; MySQL 8.4.7 by default
     * @param Charset|null $client The client encoding used to reconstruct identifiers
     * @param Span|null $source The optional original statement location
     */
    public function __construct(public readonly bool $convertXid = false, public readonly LanguageProfile $profile = new LanguageProfile(GrammarRelease::MySql847), public readonly ?Charset $client = null, public readonly ?Span $source = null, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?Span $inputSource = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        $this->shape = new \SqlSemantics\Statement\Shape\RowShape((new \SqlSemantics\Platform\MySql\Rules\Utility\ShowRows())->slots([['formatID', 'b'], ['gtrid_length', 'b'], ['bqual_length', 'b'], ['data', 'v']]));
        Check::input($profile->grammar->database() === 'mysql', 'A MySQL transaction statement uses a MySQL language profile.');
        Check::input(!$convertXid || $profile->grammar !== GrammarRelease::MySql5651, 'CONVERT XID starts in MySQL 5.7.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Records the rows that list the prepared transactions.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new AdminRows())->recover($derivation);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('XA', 'RECOVER');
        if ($this->convertXid) {
            $out->keyword('CONVERT', 'XID');
        }
    }
}
