<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Cursor\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Cursor\Handler\KeyComparison;
use SqlSemantics\Model\Statement\Cursor\Handler\ReadHandlerKeyStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(KeyComparison::class)]
#[Medium]
final class KeyComparisonTest extends TestCase
{
    public function testBindsTheComparisonOperator(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, KEY k(id))')))->bind('HANDLER t READ k < (3)');
        self::assertInstanceOf(ReadHandlerKeyStatement::class, $statement);
        self::assertSame(KeyComparison::Before, $statement->comparison);
    }
}
