<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use ZtdQuery\Config\SqlBehaviorRule;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SqlBehaviorRule::class)]
final class SqlBehaviorRuleTest extends TestCase
{
    public function testPrefixMatchesCaseInsensitive(): void
    {
        $rule = new SqlBehaviorRule('SET', UnsupportedSqlBehavior::Ignore);

        self::assertTrue($rule->matches('SET foo = 1'));
        self::assertTrue($rule->matches('set foo = 1'));
        self::assertTrue($rule->matches('Set Session foo = 1'));
    }

    public function testPrefixMatchesWithLeadingWhitespace(): void
    {
        $rule = new SqlBehaviorRule('SET', UnsupportedSqlBehavior::Ignore);

        self::assertTrue($rule->matches('  SET foo = 1'));
        self::assertTrue($rule->matches("\t\nSET foo = 1"));
    }

    public function testPrefixDoesNotMatchNonPrefix(): void
    {
        $rule = new SqlBehaviorRule('SET', UnsupportedSqlBehavior::Ignore);

        self::assertFalse($rule->matches('SELECT SET FROM t'));
        self::assertFalse($rule->matches('RESET foo'));
    }

    public function testRegexMatches(): void
    {
        $rule = new SqlBehaviorRule('/^SET\s+SESSION/i', UnsupportedSqlBehavior::Notice);

        self::assertTrue($rule->matches('SET SESSION foo = 1'));
        self::assertTrue($rule->matches('set session foo = 1'));
        self::assertTrue($rule->matches('SET  SESSION foo = 1'));
    }

    public function testRegexDoesNotMatchNonMatching(): void
    {
        $rule = new SqlBehaviorRule('/^SET\s+SESSION/i', UnsupportedSqlBehavior::Notice);

        self::assertFalse($rule->matches('SET foo = 1'));
        self::assertFalse($rule->matches('SETSESSION foo = 1'));
    }

    public function testIsRegexDetection(): void
    {
        $prefix = new SqlBehaviorRule('SET', UnsupportedSqlBehavior::Ignore);
        $regex = new SqlBehaviorRule('/^SET/', UnsupportedSqlBehavior::Ignore);

        self::assertFalse($prefix->isRegex());
        self::assertTrue($regex->isRegex());
    }

    public function testBehaviorReturnsConfiguredValue(): void
    {
        $ignoreRule = new SqlBehaviorRule('SET', UnsupportedSqlBehavior::Ignore);
        $noticeRule = new SqlBehaviorRule('BEGIN', UnsupportedSqlBehavior::Notice);
        $exceptionRule = new SqlBehaviorRule('DROP', UnsupportedSqlBehavior::Exception);

        self::assertSame(UnsupportedSqlBehavior::Ignore, $ignoreRule->behavior());
        self::assertSame(UnsupportedSqlBehavior::Notice, $noticeRule->behavior());
        self::assertSame(UnsupportedSqlBehavior::Exception, $exceptionRule->behavior());
    }

    public function testPatternReturnsOriginalPattern(): void
    {
        $rule = new SqlBehaviorRule('/^SET\s+/i', UnsupportedSqlBehavior::Ignore);

        self::assertSame('/^SET\s+/i', $rule->pattern());
    }

    public function testComplexRegexPatterns(): void
    {
        $cases = [
            'variable reference matches' => ['/@@\w+/', 'SELECT @@version', true],
            'variable reference not matches' => ['/@@\w+/', 'SELECT version()', false],
            'transaction pattern matches BEGIN' => ['/^(BEGIN|COMMIT|ROLLBACK)\b/i', 'BEGIN', true],
            'transaction pattern matches COMMIT' => ['/^(BEGIN|COMMIT|ROLLBACK)\b/i', 'COMMIT', true],
            'transaction pattern matches ROLLBACK' => ['/^(BEGIN|COMMIT|ROLLBACK)\b/i', 'ROLLBACK', true],
            'transaction pattern not matches' => ['/^(BEGIN|COMMIT|ROLLBACK)\b/i', 'SELECT * FROM t', false],
        ];

        array_walk($cases, function (array $case, string $label): void {
            [$pattern, $sql, $expected] = $case;
            $rule = new SqlBehaviorRule($pattern, UnsupportedSqlBehavior::Ignore);
            self::assertSame($expected, $rule->matches($sql), $label);
        });
    }
}
