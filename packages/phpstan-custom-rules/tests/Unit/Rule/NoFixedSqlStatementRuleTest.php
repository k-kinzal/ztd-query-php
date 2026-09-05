<?php

declare(strict_types=1);

namespace Tests\Unit\Rule;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use ZtdQuery\PhpStanCustomRules\Rule\NoFixedSqlStatementRule;

/**
 * @extends RuleTestCase<NoFixedSqlStatementRule>
 */
#[CoversClass(NoFixedSqlStatementRule::class)]
#[Medium]
final class NoFixedSqlStatementRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoFixedSqlStatementRule();
    }

    public function testDetectsFixedSqlConstructionInMySqlProvider(): void
    {
        $message = 'SQLFaker must not construct SQL statements from fixed templates; derive them from the dialect grammar.';

        $this->analyse([__DIR__ . '/../../Fixture/MySqlProvider.php'], [[$message, 11]]);
    }

    public function testDetectsFixedSqlConstructionInPostgreSqlProvider(): void
    {
        $message = 'SQLFaker must not construct SQL statements from fixed templates; derive them from the dialect grammar.';

        $this->analyse([__DIR__ . '/../../Fixture/PostgreSqlProvider.php'], [[$message, 11]]);
    }

    public function testAllowsProviderGrammarRulesAndClassesOutsideSqlFaker(): void
    {
        $this->analyse([__DIR__ . '/../../Fixture/SqliteProvider.php'], []);
        $this->analyse([__DIR__ . '/../../Fixture/Other/MySqlProvider.php'], []);
    }

    public function testDetectsFixedSqlConstructionInCommonGenerator(): void
    {
        $message = 'SQLFaker must not construct SQL statements from fixed templates; derive them from the dialect grammar.';

        $this->analyse([__DIR__ . '/../../Fixture/MySql/SqlGenerator.php'], [
            [$message, 11],
            [$message, 16],
            [$message, 21],
            [$message, 53],
        ]);
    }

    public function testDetectsComputedSqlConstructionInCommonGenerator(): void
    {
        $message = 'SQLFaker must not construct SQL statements from fixed templates; derive them from the dialect grammar.';

        $this->analyse([__DIR__ . '/../../Fixture/Sqlite/SqlGenerator.php'], [
            [$message, 15],
            [$message, 23],
            [$message, 34],
            [$message, 42],
        ]);
    }

    public function testDetectsEverySupportedStatementTemplateShape(): void
    {
        $message = 'SQLFaker must not construct SQL statements from fixed templates; derive them from the dialect grammar.';

        $this->analyse([__DIR__ . '/../../Fixture/SqlFaker/MySql/StatementTemplateHelper.php'], [
            [$message, 11],
            [$message, 16],
            [$message, 21],
            [$message, 26],
            [$message, 31],
            [$message, 36],
            [$message, 41],
            [$message, 46],
            [$message, 51],
            [$message, 57],
            [$message, 65],
            [$message, 72],
            [$message, 77],
            [$message, 84],
            [$message, 91],
            [$message, 96],
            [$message, 104],
            [$message, 127],
            [$message, 132],
            [$message, 137],
            [$message, 142],
        ]);
    }

    public function testDetectsFixedSqlConstructionInSqlFakerNamespace(): void
    {
        $message = 'SQLFaker must not construct SQL statements from fixed templates; derive them from the dialect grammar.';

        $this->analyse([__DIR__ . '/../../Fixture/SqlFaker/NamespaceTemplate.php'], [[$message, 7]]);
    }
}
