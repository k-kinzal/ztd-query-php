<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rendering\TransactionSql;
use SqlSemantics\Platform\MySql\Rules\Server\Magnitudes;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\TransactionStatement;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Source\Span;

/**
 * `XA PREPARE xid`: a request to prepare an XA transaction for commit.
 *
 * Mirrors Sql_cmd_xa_prepare. Rule: MYSQL-XA-PREPARE-001. The statement names no
 * relation and has no facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/xa-statements.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the request
 *     $xa = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("xa prepare 'g'");
 *     [$xa->toString(), $xa->xid->transaction->value === 'g'] // => ["XA PREPARE 'g'", true]
 * @example Refusing a format identifier outside the selected release
 *     new \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaPrepare(new \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\Xid(new \SqlSemantics\Platform\MySql\Statement\Literal\Text('a'), new \SqlSemantics\Platform\MySql\Statement\Literal\Text('b'), new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('9223372036854775808'))) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class XaPrepare implements TransactionStatement
{
    use Snapshot;
    use TransactionSql;

    /**
     * @param Xid $xid The transaction identifier
     * @param LanguageProfile $profile The selected grammar and lexical interpretation; MySQL 8.4.7 by default
     * @param Charset|null $client The client encoding used to reconstruct identifiers
     * @param Span|null $source The optional original statement location
     */
    public function __construct(public readonly Xid $xid, public readonly LanguageProfile $profile = new LanguageProfile(GrammarRelease::MySql847), public readonly ?Charset $client = null, public readonly ?Span $source = null)
    {
        Check::input($profile->grammar->database() === 'mysql', 'A MySQL transaction statement uses a MySQL language profile.');
        (new Magnitudes())->identifier($xid, $profile->grammar);
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Refuses a format identifier the release rejects; the request names no relation and no value.
     *
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the release does not read the format identifier
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new Magnitudes())->identifier($this->xid, $derivation->context->profile->grammar);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('XA', 'PREPARE')->node($this->xid);
    }
}
