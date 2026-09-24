<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Cursor\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Cursor\Handler\HandlerTarget;
use SqlSemantics\Model\Statement\Cursor\Handler\OpenHandlerStatement;
use SqlSemantics\Model\Statement\Cursor\Handler\ReadHandlerStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(HandlerTarget::class)]
#[Medium]
final class HandlerTargetTest extends TestCase
{
    public function testValidateAcceptsAResolvedHandlerAndCondition(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('HANDLER t READ FIRST WHERE id = 1');
        self::assertInstanceOf(ReadHandlerStatement::class, $statement);
        HandlerTarget::validate($statement->origin, $statement->handler, $statement->where);
        $this->addToAssertionCount(1);
    }

    public function testValidateRejectsAnAliasedHandler(): void
    {
        $open = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('HANDLER t OPEN h');
        self::assertInstanceOf(OpenHandlerStatement::class, $open);
        $this->expectException(InvalidStructure::class);
        HandlerTarget::validate($open->origin, $open->table);
    }

    public function testValidateRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('HANDLER t READ FIRST');
        self::assertInstanceOf(ReadHandlerStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        HandlerTarget::validate(new Origin('s0', $statement->source, Dialect::Sqlite), $statement->handler);
    }
}
