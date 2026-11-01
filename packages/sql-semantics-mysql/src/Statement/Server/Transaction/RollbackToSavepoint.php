<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Transaction;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rendering\TransactionSql;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Source\Span;

/**
 * `ROLLBACK [WORK] TO [SAVEPOINT] name`: a request to undo the changes made after a savepoint.
 *
 * Mirrors SQLCOM_ROLLBACK_TO_SAVEPOINT. Rule: MYSQL-ROLLBACK-TO-SAVEPOINT-001. The savepoint is a name of the
 * session's current transaction, which no declaration context holds; the
 * server compares savepoint names case-insensitively. WORK and SAVEPOINT are optional; their presence is retained. The statement
 * names no relation and has no facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/savepoint.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Naming the savepoint
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('rollback work to sp1');
 *     [$statement->toString(), $statement->savepoint->value] // => ['ROLLBACK WORK TO sp1', 'sp1']
 */
final class RollbackToSavepoint extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements TransactionStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;
    use TransactionSql;

    /**
     * The syntax notice of the savepoint operand, determined by its spelling and release.
     */
    public readonly ?\SqlSemantics\Platform\MySql\Statement\Notice\Deprecation $nameWarning;

    /**
     * @param Name $savepoint The savepoint name
     * @param bool $work Whether WORK is written
     * @param bool $savepointWritten Whether SAVEPOINT is written
     * @param LanguageProfile $profile The selected grammar and lexical interpretation; MySQL 8.4.7 by default
     * @param Charset|null $client The client encoding used to reconstruct identifiers
     * @param Span|null $source The optional original statement location
     */
    public function __construct(public readonly Name $savepoint, public readonly bool $work = false, public readonly bool $savepointWritten = true, public readonly LanguageProfile $profile = new LanguageProfile(GrammarRelease::MySql847), public readonly ?Charset $client = null, public readonly ?Span $source = null, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?Span $inputSource = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        Check::input($profile->grammar->database() === 'mysql', 'A MySQL transaction statement uses a MySQL language profile.');
        $this->nameWarning = \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation::unqualifiedName($savepoint, $profile->grammar);
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
        $out->keyword('ROLLBACK');
        if ($this->work) {
            $out->keyword('WORK');
        }
        $out->keyword('TO');
        if ($this->savepointWritten) {
            $out->keyword('SAVEPOINT');
        }
        $out->name($this->savepoint, NameUse::Identifier);
    }
}
