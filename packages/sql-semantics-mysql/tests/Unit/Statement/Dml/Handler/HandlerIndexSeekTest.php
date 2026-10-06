<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexSeek;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\KeyComparison;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\OpenHandler;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(HandlerIndexSeek::class)]
#[Medium]
final class HandlerIndexSeekTest extends TestCase
{
    public function testDeriveStatementDerivesTheKeyValues(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('HANDLER h READ i >= (1, 2)');
        self::assertInstanceOf(HandlerIndexSeek::class, $operation->statement);

        self::assertEquals(Nullability::NotNull, $operation->facts->scalar($operation->statement->values[0])->nullability);
    }

    public function testRenderWritesTheSeek(): void
    {
        self::assertSame('HANDLER h READ i < (1) WHERE b = 2', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('handler h read i < (1) where b = 2')->toString());
    }

    public function testRenderRejectsAnEmptyKey(): void
    {
        $this->expectExceptionMessage('An index seek compares at least one key value.');

        new HandlerIndexSeek(new OpenHandler(new Name('h')), new Name('i'), KeyComparison::Equal, []);
    }
}
