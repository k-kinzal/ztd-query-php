<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Config\SqlBehaviorRule;
use ZtdQuery\Config\UnsupportedSqlBehavior;

final class SqlBehaviorRuleTest extends TestCase
{
    public function testPrefixMatchesCaseInsensitive(): void
    {
        $rule = new SqlBehaviorRule('SET', UnsupportedSqlBehavior::Ignore);

        $this->assertTrue($rule->matches('SET foo = 1'));
        $this->assertTrue($rule->matches('set foo = 1'));
        $this->assertTrue($rule->matches('Set Session foo = 1'));
    }

    public function testPrefixMatchesWithLeadingWhitespace(): void
    {
        $rule = new SqlBehaviorRule('SET', UnsupportedSqlBehavior::Ignore);

        $this->assertTrue($rule->matches('  SET foo = 1'));
        $this->assertTrue($rule->matches("\t\nSET foo = 1"));
    }

    public function testPrefixDoesNotMatchNonPrefix(): void
    {
        $rule = new SqlBehaviorRule('SET', UnsupportedSqlBehavior::Ignore);

        $this->assertFalse($rule->matches('SELECT SET FROM t'));
        $this->assertFalse($rule->matches('RESET foo'));
    }

    public function testRegexMatches(): void
    {
        $rule = new SqlBehaviorRule('/^SET\s+SESSION/i', UnsupportedSqlBehavior::Notice);

        $this->assertTrue($rule->matches('SET SESSION foo = 1'));
        $this->assertTrue($rule->matches('set session foo = 1'));
        $this->assertTrue($rule->matches('SET  SESSION foo = 1'));
    }

    public function testRegexDoesNotMatchNonMatching(): void
    {
        $rule = new SqlBehaviorRule('/^SET\s+SESSION/i', UnsupportedSqlBehavior::Notice);

        $this->assertFalse($rule->matches('SET foo = 1'));
        $this->assertFalse($rule->matches('SETSESSION foo = 1'));
    }

    public function testIsRegexDetection(): void
    {
        $prefix = new SqlBehaviorRule('SET', UnsupportedSqlBehavior::Ignore);
        $regex = new SqlBehaviorRule('/^SET/', UnsupportedSqlBehavior::Ignore);

        $this->assertFalse($prefix->isRegex());
        $this->assertTrue($regex->isRegex());
    }

    public function testBehaviorReturnsConfiguredValue(): void
    {
        $ignoreRule = new SqlBehaviorRule('SET', UnsupportedSqlBehavior::Ignore);
        $noticeRule = new SqlBehaviorRule('BEGIN', UnsupportedSqlBehavior::Notice);
        $exceptionRule = new SqlBehaviorRule('DROP', UnsupportedSqlBehavior::Exception);

        $this->assertSame(UnsupportedSqlBehavior::Ignore, $ignoreRule->behavior());
        $this->assertSame(UnsupportedSqlBehavior::Notice, $noticeRule->behavior());
        $this->assertSame(UnsupportedSqlBehavior::Exception, $exceptionRule->behavior());
    }

    public function testPatternReturnsOriginalPattern(): void
    {
        $rule = new SqlBehaviorRule('/^SET\s+/i', UnsupportedSqlBehavior::Ignore);

        $this->assertSame('/^SET\s+/i', $rule->pattern());
    }

    #[DataProvider('providerComplexRegexPatterns')]
    public function testComplexRegexPatterns(string $pattern, string $sql, bool $expected): void
    {
        $rule = new SqlBehaviorRule($pattern, UnsupportedSqlBehavior::Ignore);

        $this->assertSame($expected, $rule->matches($sql));
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function providerComplexRegexPatterns(): array
    {
        return [
            'variable reference matches' => ['/@@\w+/', 'SELECT @@version', true],
            'variable reference not matches' => ['/@@\w+/', 'SELECT version()', false],
            'transaction pattern matches BEGIN' => ['/^(BEGIN|COMMIT|ROLLBACK)\b/i', 'BEGIN', true],
            'transaction pattern matches COMMIT' => ['/^(BEGIN|COMMIT|ROLLBACK)\b/i', 'COMMIT', true],
            'transaction pattern matches ROLLBACK' => ['/^(BEGIN|COMMIT|ROLLBACK)\b/i', 'ROLLBACK', true],
            'transaction pattern not matches' => ['/^(BEGIN|COMMIT|ROLLBACK)\b/i', 'SELECT * FROM t', false],
        ];
    }
}
