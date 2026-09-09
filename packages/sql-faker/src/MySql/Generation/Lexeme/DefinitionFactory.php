<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Lexeme;

use SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\IntegerLexemeGenerator;
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
                new ChoiceLexemeGenerator(
                    new SizeNumberLexemeGenerator(),
                    new BoundedIntegerLexemeGenerator('KEY_ALGORITHM_NUMBER', 1, 2, ['1', '2'], 'sql/sql_yacc.yy:opt_key_algo'),
                    new IntegerLexemeGenerator('AVG_ROW_LENGTH_NUMBER', '0', '4294967295', ['0', '1', '4294967295'], 'sql/sql_yacc.yy:AVG_ROW_LENGTH'),
                    new IntegerLexemeGenerator('YEAR_WIDTH_NUMBER', '4', '4', ['4'], 'sql/sql_yacc.yy:YEAR_SYM'),
                    new BoundedIntegerLexemeGenerator('KEY_BLOCK_SIZE_NUMBER', 0, 65535, ['0', '1', '65535'], 'sql/sql_yacc.yy:KEY_BLOCK_SIZE'),
                    new BoundedIntegerLexemeGenerator('STATS_SAMPLE_PAGES_NUMBER', 1, 65535, ['1', '65535'], 'sql/sql_yacc.yy:STATS_SAMPLE_PAGES'),
                    new BoundedIntegerLexemeGenerator('SOURCE_DELAY_NUMBER', 0, 2147483647, ['0', '1', '2147483647'], 'sql/sql_yacc.yy:SOURCE_DELAY'),
                    (new ValueDefinitions())->create(),
                    (new DollarStringDefinitions())->create($version),
                    (new SymbolDefinitions())->create($version)
                ),
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
