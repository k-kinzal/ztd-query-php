<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\Notice;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Fact\Warning;

/**
 * The language, result and conditions of a statement.
 * Operand meanings and source locations remain on the SQL occurrences they describe.
 * No declaration context, statement wrapper or node-indexed facts are retained.
 *
 * @visibility public
 * @example Reading the result of an analyzed statement
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1 AS n');
 *     $query->statementInformation()->output->fields()->at(0)->name->value // => 'n'
 */
final class StatementSemantics
{
    use Snapshot;

    /**
 * @var list<Table> Declarations introduced by this definition
 */
    public readonly array $declarations;
    /**
 * @var list<Diagnostic> Conditions on this statement
 */
    public readonly array $diagnostics;
    /**
 * @var list<Notice> Notices with their semantic owners
 */
    public readonly array $notices;
    /**
 * @var list<Warning> Conditions raised without failing
 */
    public readonly array $warnings;

    /**
     * @param list<Table> $declarations Declarations introduced by this definition
     * @param list<Diagnostic> $diagnostics Conditions on this statement
     * @param list<Warning|Notice> $warnings Notices in emission order
     */
    public function __construct(public readonly LanguageProfile $profile, public readonly ?QueryFact $output = null, array $declarations = [], array $diagnostics = [], array $warnings = [])
    {
        $this->declarations = Check::listOf($declarations, Table::class, 'Definitions contribute table declarations.');
        $this->diagnostics = Check::listOf($diagnostics, Diagnostic::class, 'Statement conditions are diagnostics.');
        $this->notices = array_map(static fn (Warning|Notice $warning): Notice => $warning instanceof Notice ? $warning : new Notice($warning), $warnings);
        $this->warnings = array_map(static fn (Notice $notice): Warning => $notice->warning, $this->notices);
    }
}
