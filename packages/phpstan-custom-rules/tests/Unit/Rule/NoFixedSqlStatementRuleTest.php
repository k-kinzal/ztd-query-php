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

    public function testDetectsFixedSqlConstructionInProvidersAndGenerators(): void
    {
        $message = 'SQLFaker Providers and SqlGenerators must not construct SQL statements from fixed templates; derive them from the dialect grammar.';

        $this->analyse([
            __DIR__ . '/../../Fixture/MySqlProvider.php',
            __DIR__ . '/../../Fixture/PostgreSqlProvider.php',
            __DIR__ . '/../../Fixture/SqliteProvider.php',
            __DIR__ . '/../../Fixture/MySql/SqlGenerator.php',
        ], [
            [$message, 11],
            [$message, 11],
            [$message, 11],
        ]);
    }
}
