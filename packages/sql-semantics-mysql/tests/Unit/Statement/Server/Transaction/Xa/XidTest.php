<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Transaction\Xa;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\Xid;

#[CoversClass(Xid::class)]
#[Medium]
final class XidTest extends TestCase
{
    public function testRenderWritesThePartsSeparatedByCommas(): void
    {
        self::assertSame("XA START 'g', b'1', x'7fffffffffffffff'", (new Semantics(Dialect::MySql))->analyze("xa start 'g', b'1', 0x7fffffffffffffff")->toString());
    }

    public function testTransactionIsTheFirstPart(): void
    {
        self::assertSame('g', (new Xid(new Text('g')))->transaction->value);
    }
    /**
     * @return iterable<string, array{Text}>
     */
    public static function providerOversizedParts(): iterable
    {
        yield 'quoted bytes' => [new Text(str_repeat('a', 65))];
        yield 'multibyte text' => [new Text(str_repeat('猫', 22))];
        yield 'hexadecimal bytes' => [new Text(str_repeat('f', 129), \SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal)];
        yield 'bit bytes' => [new Text(str_repeat('1', 513), \SqlSemantics\Platform\MySql\Statement\Literal\Radix::Bit)];
    }

    #[DataProvider('providerOversizedParts')]
    public function testValueRefusesOversizedTransactionsBeforePublication(Text $text): void
    {
        $this->expectExceptionMessage('A global transaction identifier holds at most 64 bytes.');
        new Xid($text);
    }

    #[DataProvider('providerOversizedParts')]
    public function testValueRefusesOversizedBranchesBeforePublication(Text $text): void
    {
        $this->expectExceptionMessage('A branch qualifier holds at most 64 bytes.');
        new Xid(new Text('g'), $text);
    }

}
