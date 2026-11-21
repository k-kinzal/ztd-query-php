<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Contract;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * Fixed grammar artifacts, semantic specification revision, and lexical interpretation.
 *
 * A revision identifies the contract being implemented, not completed implementation
 * or formal proof. No parser, cache, connection, clock, or mutable settings survive here.
 * @visibility public
 * @example Selecting the fixed SQLite observation profile
 *     (new \SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile(\SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::PostgreSql172))->ruleRevision // => 'SQLSEM-DESIGN-001/1.0'
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
        Check::input($lexical->equals(new LexicalSettings()), 'Only the MySQL profiles accept MySQL lexical settings.');
        $this->ruleRevision = 'SQLSEM-DESIGN-001/1.0';
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
