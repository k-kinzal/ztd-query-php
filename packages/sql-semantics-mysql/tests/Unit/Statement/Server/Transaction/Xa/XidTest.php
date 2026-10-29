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
        self::assertSame("XA START 'g', b'1', 0x7fffffffffffffff", (new Semantics(Dialect::MySql))->analyze("xa start 'g', b'1', 0x7fffffffffffffff")->toString());
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
    public function testProblemRetainsOversizedTransactions(Text $text): void
    {
        $identifier = new Xid($text);
        self::assertSame($text, $identifier->transaction);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedXaIdentifier::class, $identifier->problem(\SqlSemantics\Contract\GrammarRelease::MySql847));
    }

    #[DataProvider('providerOversizedParts')]
    public function testProblemRetainsOversizedBranches(Text $text): void
    {
        $identifier = new Xid(new Text('g'), $text);
        self::assertSame($text, $identifier->branch);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedXaIdentifier::class, $identifier->problem(\SqlSemantics\Contract\GrammarRelease::MySql847));
    }
    public function testDeriveRecordsTheCompleteIdentifierCondition(): void
    {
        $sql = "XA ROLLBACK '" . str_repeat('g', 65) . "'";
        $statement = (new Semantics(Dialect::MySql))->analyze($sql);
        self::assertSame($sql, $statement->toString());
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $statement->warnings()[0]);
        self::assertEquals(new \SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedXaIdentifier(strlen($sql), true), $statement->diagnostics()[0]);
    }

    public function testProblemKeepsTheReleaseSpecificNumericBoundary(): void
    {
        $identifier = new Xid(new Text('g'), new Text('b'), new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('9223372036854775808'));
        self::assertNull($identifier->problem(\SqlSemantics\Contract\GrammarRelease::MySql5651));
        self::assertEquals(new \SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedXaIdentifier(0), $identifier->problem(\SqlSemantics\Contract\GrammarRelease::MySql847));
        self::assertNull((new Xid(new Text(str_repeat('g', 64))))->problem(\SqlSemantics\Contract\GrammarRelease::MySql847));
    }

}
