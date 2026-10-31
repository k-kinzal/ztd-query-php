<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Transaction;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Statement;

/**
 * A request to start a named, nestable transaction.
 *
 * Rule: SQLITE-SAVEPOINT-001. The name need not be unique; a later RELEASE or
 * ROLLBACK TO finds the most recent savepoint with the name.
 * Source: https://sqlite.org/lang_savepoint.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a savepoint name
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SAVEPOINT "before import"')->name->value // => 'before import'
 */
final class Savepoint extends \SqlSemantics\Statement\SqlStatement implements Statement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Name $name The savepoint name
     */
    public function __construct(public readonly Name $name, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, ?\SqlSemantics\Statement\StatementSemantics $statementInformation = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
    }

    /**
     * Derives nothing: the request depends on no declaration.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('SAVEPOINT')->name($this->name, NameUse::Label);
    }
}
