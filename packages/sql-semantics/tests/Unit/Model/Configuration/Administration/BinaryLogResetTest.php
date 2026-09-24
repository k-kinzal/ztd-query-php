<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Administration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Administration\BinaryLogReset;
use SqlSemantics\Model\Statement\Server\Administration\CloneRemoteStatement;
use SqlSemantics\Model\Statement\Server\Administration\ResetServerStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(BinaryLogReset::class)]
#[Medium]
final class BinaryLogResetTest extends TestCase
{
    public function testAvailableInRequiresMySql8ForAnIndex(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('RESET BINARY LOGS AND GTIDS TO 7');
        self::assertInstanceOf(ResetServerStatement::class, $statement);
        self::assertInstanceOf(BinaryLogReset::class, $statement->targets[0]);
        self::assertFalse($statement->targets[0]->availableIn(50744));
        self::assertTrue($statement->targets[0]->availableIn(80044));
        self::assertTrue((new BinaryLogReset())->availableIn(50651));
    }

    public function testRejectsAnIndexOutOfRange(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CLONE INSTANCE FROM u@h:2000000001 IDENTIFIED BY 'p'");
        self::assertInstanceOf(CloneRemoteStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new BinaryLogReset($statement->port);
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['1'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['2000000000'])]
    public function testAcceptsTheEdgesOfTheIndexRange(string $index): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('RESET BINARY LOGS AND GTIDS TO ' . $index);
        self::assertInstanceOf(ResetServerStatement::class, $statement);
        self::assertInstanceOf(BinaryLogReset::class, $statement->targets[0]);
        self::assertSame($index, $statement->targets[0]->firstIndex?->text);
        self::assertTrue($statement->targets[0]->availableIn(80000));
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['1.5'])]
    #[\PHPUnit\Framework\Attributes\TestWith(["'7'"])]
    public function testRejectsAnIndexThatIsNoInteger(string $index): void
    {
        $literal = (new \SqlSemantics\Binding\LiteralBinder(Dialect::MySql))->bind((new \SqlSemantics\Ast\DialectParser(Dialect::MySql))->parse('SELECT ' . $index)->tokens()[1]);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $literal);
        $this->expectException(InvalidStructure::class);
        new BinaryLogReset($literal);
    }

    public function testRejectsIndexZero(): void
    {
        $literal = (new \SqlSemantics\Binding\LiteralBinder(Dialect::MySql))->bind((new \SqlSemantics\Ast\DialectParser(Dialect::MySql))->parse('SELECT 0')->tokens()[1]);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $literal);
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('A binary log index is an integer from 1 to 2000000000.');
        new BinaryLogReset($literal);
    }
}
