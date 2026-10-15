<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Lowering\Account\SyntaxFactors;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\AccountNameFailure;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\InvalidFactorPair;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\NumberOutOfRange;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(SyntaxFactors::class)]
#[Medium]
final class SyntaxFactorsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, \SqlSemantics\Statement\Fact\Diagnostic}>
     */
    public static function providerRefusals(): iterable
    {
        yield 'factor before syntax' => ['ALTER USER u 02 FACTOR UNREGISTER BROKEN', new NumberOutOfRange('nth factor', '02', 'ER_WRONG_VALUE')];
        yield 'pair before next account' => ['ALTER USER u DROP 2 FACTOR DROP 2 FACTOR, v DROP 0 FACTOR', new InvalidFactorPair(true)];
        yield 'preceding account' => ["ALTER USER 'u'@'bad@host' 1 FACTOR UNREGISTER", new AccountNameFailure('bad@host', true)];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerRefusals')]
    public function testCompletedStopsAtTheFirstInvalidOperand(string $sql, \SqlSemantics\Statement\Fact\Diagnostic $problem): void
    {
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql847));

        self::assertEquals([new ParseFailure($problem, true)], array_column($notices, 1));
    }

    public function testAccountRetainsTheWholeOperandAndItsLimit(): void
    {
        $checks = new SyntaxFactors();
        self::assertNull($checks->account(new AccountName(new Name('u'), new Name('example.com'))));
        self::assertEquals(new ParseFailure(new AccountNameFailure(str_repeat('あ', 33), false, 32), true), $checks->account(new AccountName(new Name(str_repeat('あ', 33)))));
        self::assertEquals(new ParseFailure(new AccountNameFailure(str_repeat('h', 256), true, 255), true), $checks->account(new AccountName(new Name('u'), new Name(str_repeat('h', 256)))));
    }
}
