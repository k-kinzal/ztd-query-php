<?php

declare(strict_types=1);

namespace SqlSemantics\Contract;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Snapshot;

/**
 * Fixed grammar artifacts, semantic specification revision, and lexical interpretation.
 *
 * A revision identifies the contract being implemented, not completed implementation
 * or formal proof. No parser, cache, connection, clock, or mutable settings survive here.
 * @visibility public
 * @example Selecting the fixed SQLite observation profile
 *     (new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::Sqlite3472))->ruleRevision // => 'SQLSEM-DESIGN-001/1.0'
 */
final class LanguageProfile
{
    use Snapshot;

    /**
     * The specification revision, independently of implementation completion.
     */
    public readonly string $ruleRevision;

    /**
     * The grammar release fixes its artifact digests and its database family.
     */
    public function __construct(
        public readonly GrammarRelease $grammar,
        public readonly LexicalSettings $lexical = new LexicalSettings(),
        public readonly ParameterStyle $parameters = ParameterStyle::Native,
    ) {
        Check::input($grammar->database() === 'mysql' || $lexical->equals(new LexicalSettings()), 'Only the MySQL profiles accept MySQL lexical settings.');
        $this->ruleRevision = 'SQLSEM-DESIGN-001/1.0';
    }

    /**
     * Tells whether declarations made under another profile hold under this one: the same grammar release and rule revision.
     *
     * Lexical settings and the parameter style change only how SQL text reads, so a table declared
     * under one sql_mode keeps its declaration when a session reads SQL under another.
     */
    public function sharesDeclarationsWith(self $other): bool
    {
        return $this->grammar === $other->grammar && $this->ruleRevision === $other->ruleRevision;
    }

    /**
     * Profile compatibility includes the fixed artifacts and effective lexical settings.
     */
    public function compatibleWith(self $other): bool
    {
        return $this->grammar === $other->grammar
            && $this->lexical->equals($other->lexical)
            && $this->parameters === $other->parameters
            && $this->ruleRevision === $other->ruleRevision;
    }
}
