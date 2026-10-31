<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Prepared;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * The complete interpretation domain of one prepared SQL input.
 *
 * No source buffer, parser, declaration context or Facts registry survives here.
 * The active interpretation alone contributes runtime metadata dependencies.
 * Ordinary analysis still publishes the statement itself, not this definition.
 *
 * @visibility public
 * @example Rejecting an incomplete preparation domain
 *     $profile = new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Interpretation(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847), \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::known('utf8mb4'));
 *     new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Definition($profile, null, []) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Rejecting a missing interpretation
 *     $profile = new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Interpretation(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847), \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::known('utf8mb4'));
 *     $statement = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]));
 *     new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Definition($profile, null, [new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Alternative($profile, $statement)]) // throws \SqlSemantics\Diagnostic\InvalidConstruction: missing interpretation
 * @example Rejecting duplicate interpretations
 *     $profile = new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Interpretation(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847), \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::known('utf8mb4'));
 *     $statement = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]));
 *     $alternative = new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Alternative($profile, $statement);
 *     new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Definition($profile, null, [$alternative, $alternative]) // throws \SqlSemantics\Diagnostic\InvalidConstruction: exactly one alternative
 */
final class Definition
{
    use Snapshot;

    /**
     * @var array<string, Alternative> Exactly one alternative per effective interpretation
     */
    public readonly array $alternatives;

    /**
     * @param Interpretation $initial The initial preparation's lexical and client settings
     * @param Name|null $database The original default database, or none
     * @param list<Alternative> $alternatives Complete interpretations of the same written input
     */
    public function __construct(public readonly Interpretation $initial, public readonly ?Name $database, array $alternatives)
    {
        $indexed = [];
        foreach (Check::listOf($alternatives, Alternative::class, 'Preparation alternatives form an ordered list.', 1) as $alternative) {
            $language = $alternative->interpretation->language;
            Check::input($language->grammar === $initial->language->grammar && $language->parameters === $initial->language->parameters, 'All preparation alternatives use one release and parameter style.');
            $key = $alternative->interpretation->key();
            Check::input(!isset($indexed[$key]), 'A preparation has exactly one alternative per interpretation.');
            $indexed[$key] = $alternative;
        }
        foreach (Interpretation::all($initial->language->grammar, $initial->language->parameters) as $interpretation) {
            Check::input(isset($indexed[$interpretation->key()]), 'The preparation is missing interpretation ' . $interpretation->key() . '.');
        }
        Check::input(!$indexed[$initial->key()]->statement instanceof SyntaxRefusal, 'An accepted preparation has a grammatical initial interpretation.');
        $this->alternatives = $indexed;
    }

    /**
     * Selects an existing interpretation; there is no default or source fallback.
     */
    public function select(Interpretation $interpretation): Alternative
    {
        Check::input($interpretation->language->grammar === $this->initial->language->grammar && $interpretation->language->parameters === $this->initial->language->parameters, 'Repreparation retains its release and parameter style.');
        return $this->alternatives[$interpretation->key()];
    }
}
