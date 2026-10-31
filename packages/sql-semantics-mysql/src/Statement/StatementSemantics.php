<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement;

use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\Notice;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Fact\Warning;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * A statement's language, result and conditions, without a catalog or node index.
 *
 * Expression types and relation bindings belong to the expressions and relations.
 * The input declaration context is used during construction and is not retained.
 *
 * @visibility public
 * @example Inspecting the results of a concrete statement
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT 1 AS value');
 *     $statement->statementInformation()->field(0)->name->value // => 'value'
 */
final class StatementSemantics
{
    use Snapshot;

    /**

     * @var list<Table> Declarations contributed by this definition

     */
    public readonly array $declarations;

    /**

     * @var list<Diagnostic> Conditions raised by this statement

     */
    public readonly array $diagnostics;

    /**

     * @var list<Warning> Notices raised by this statement

     */
    public readonly array $warnings;

    /**

     * @var list<Notice> Notices in emission order, with their SQL owners

     */
    public readonly array $notices;

    /**
     * @param list<Table> $declarations Declarations contributed by a definition
     * @param list<Diagnostic> $diagnostics Conditions belonging to this statement
     * @param list<Warning|Notice> $warnings Notices belonging to this statement
     */
    public function __construct(
        public readonly LanguageProfile $profile,
        public readonly ?Charset $client = null,
        public readonly ?QueryFact $output = null,
        array $declarations = [],
        array $diagnostics = [],
        array $warnings = [],
        public readonly ?Type\Resolved\Collation $connection = null,
    ) {
        Check::input($profile->grammar->database() === 'mysql', 'A MySQL statement uses a MySQL language profile.');
        $this->declarations = Check::listOf($declarations, Table::class, 'A definition contributes table declarations.');
        $this->diagnostics = Check::listOf($diagnostics, Diagnostic::class, 'Statement conditions are diagnostics.');
        $notices = [];
        foreach ($warnings as $warning) {
            $notices[] = $warning instanceof Notice ? $warning : new Notice($warning);
        }
        $this->notices = $notices;
        $this->warnings = array_map(static fn (Notice $notice): Warning => $notice->warning, $notices);
    }

    /**
     * Returns the stored instruction which raises a notice.
     */
    public function warningInstruction(Warning $warning): ?Node
    {
        foreach ($this->notices as $notice) {
            if ($notice->warning === $warning) {
                return $notice->instruction;
            }
        }
        return null;
    }

    /**
     * Returns the SQL occurrence which raises a notice.
     */
    public function warningSubject(Warning $warning): ?Node
    {
        foreach ($this->notices as $notice) {
            if ($notice->warning === $warning) {
                return $notice->subject instanceof Node ? $notice->subject : null;
            }
        }
        return null;
    }

    /**
     * Selects a nested query's result and conditions without deriving it again.
     */
    public function forQuery(\SqlSemantics\Statement\Query $query): self
    {
        $output = $query->queryInformation();
        return new self($this->profile, $this->client, $output, $this->declarations, $output->diagnostics, $this->notices, $this->connection);
    }

    /**
     * Returns the output field at its result position.
     */
    public function field(int $position): \SqlSemantics\Statement\Shape\Field
    {
        $fields = $this->output?->fields();
        Check::input($fields !== null, 'The statement has a complete result column list.');
        return $fields->at($position);
    }
}
