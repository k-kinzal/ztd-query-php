<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Lexeme;

use SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Output\CandidateResolver;
use SqlFaker\Grammar\Generation\Output\ReverseLexemeGenerator;
use SqlFaker\Grammar\Generation\Spacing\CombinedSpacingRule;
use SqlFaker\Grammar\Generation\Spacing\KeywordPhraseSpacingRule;
use SqlFaker\Grammar\Generation\Version\VersionCase;
use SqlFaker\Grammar\Generation\Version\VersionedLexemeGenerator;
use SqlFaker\MySql\Generation\Spacing\CloneAddressSpacingRule;
use SqlFaker\MySql\Generation\Spacing\FunctionSpacingRule;
use SqlFaker\MySql\Generation\Spacing\QualifiedNameSpacingRule;
use SqlFaker\MySql\Generation\Spacing\VariableSpacingRule;

/**
 * Binds the exact release's registration data to executable scanner and spacing definitions.
 */
final class DefinitionFactory
{
    /**
     * @param array<string, list<string>> $symbols
     * @param array<string, list<string>> $functions
     */
    public function lexemes(string $version, array $symbols, array $functions): LexemeGenerator
    {
        return new ChoiceLexemeGenerator(
            (new ContextualValueDefinitions())->create($version),
            (new KeywordDefinitions())->create($version, new KeywordLexemeGenerator($symbols, $functions)),
            new VersionedLexemeGenerator($version, new VersionCase(
                ['mysql-5.6.51', 'mysql-5.7.44', 'mysql-8.0.44', 'mysql-8.1.0', 'mysql-8.2.0', 'mysql-8.3.0', 'mysql-8.4.7', 'mysql-9.0.1', 'mysql-9.1.0'],
                new ChoiceLexemeGenerator((new ValueDefinitions())->create(), (new DollarStringDefinitions())->create($version), (new SymbolDefinitions())->create($version)),
                'mysql-default-mode-scanner',
            )),
        );
    }

    /**
     * @param array<string, list<string>> $symbols
     * @param array<string, list<string>> $functions
     */
    public function create(string $version, array $symbols, array $functions): ReverseLexemeGenerator
    {
        return new ReverseLexemeGenerator($this->lexemes($version, $symbols, $functions), new CandidateResolver(
            new CombinedSpacingRule(
                new FunctionSpacingRule(),
                new VariableSpacingRule(),
                new QualifiedNameSpacingRule(),
                new CloneAddressSpacingRule(),
                new KeywordPhraseSpacingRule(),
            ),
        ), $version, 'MySQL');
    }
}
