<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Server\ResourceGroup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Execution\DoExpressionsStatement;
use SqlSemantics\Model\Statement\Server\ResourceGroup\SetResourceGroupStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SetResourceGroupStatement::class)]
#[Medium]
final class SetResourceGroupStatementTest extends TestCase
{
    public function testWithOriginRetainsTheThreads(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SET RESOURCE GROUP g FOR 1, 0x2');
        self::assertInstanceOf(SetResourceGroupStatement::class, $statement);
        self::assertSame('SET RESOURCE GROUP `g` FOR 1, 0x2', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOrigin($statement->origin)));
    }

    public function testWithNameAssignsAnotherGroup(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SET RESOURCE GROUP g');
        self::assertInstanceOf(SetResourceGroupStatement::class, $statement);
        self::assertSame('SET RESOURCE GROUP `h`', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withName('h')));
    }

    public function testWithThreadsRequiresUnsignedIntegerLiterals(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('SET RESOURCE GROUP g FOR 3');
        $text = $binder->bind("DO 'x'");
        self::assertInstanceOf(SetResourceGroupStatement::class, $statement);
        self::assertInstanceOf(DoExpressionsStatement::class, $text);
        self::assertSame('SET RESOURCE GROUP `g`', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withThreads([])));
        $literal = $text->expressions[0];
        self::assertInstanceOf(Literal::class, $literal);
        $this->expectException(InvalidStructure::class);
        $statement->withThreads([$literal]);
    }
}
