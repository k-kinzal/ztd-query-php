<?php

declare(strict_types=1);

namespace Tests\Unit\Rule;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use ZtdQuery\PhpStanCustomRules\Rule\SqlFakerProviderDelegationRule;

/**
 * @extends RuleTestCase<SqlFakerProviderDelegationRule>
 */
#[CoversClass(SqlFakerProviderDelegationRule::class)]
#[Medium]
final class SqlFakerProviderDelegationRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new SqlFakerProviderDelegationRule();
    }

    public function testRejectsProviderOwnedSqlAndLexemeConstruction(): void
    {
        $message = 'SQLFaker Providers must delegate generation to SqlGenerator::generate() with a GenerationPlan.';

        $this->analyse([
            __DIR__ . '/../../Fixture/MySqlProvider.php',
            __DIR__ . '/../../Fixture/PostgreSqlProvider.php',
            __DIR__ . '/../../Fixture/SqliteProvider.php',
            __DIR__ . '/../../Fixture/Other/MySqlProvider.php',
            __DIR__ . '/../../Fixture/DelegatingProviders.php',
            __DIR__ . '/../../Fixture/SqlFaker/FutureProvider.php',
        ], [
            [$message, 11],
            [$message, 11],
            [$message, 11],
            [$message, 26],
            [$message, 29],
            [$message, 43],
            [$message, 48],
            [$message, 60],
            [$message, 66],
            [$message, 70],
            [$message, 81],
            [$message, 89],
            [$message, 95],
            [$message, 101],
            [$message, 105],
            [$message, 11],
        ]);
    }
}
