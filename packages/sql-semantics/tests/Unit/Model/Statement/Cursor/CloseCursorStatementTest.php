<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Cursor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Model\Statement\Cursor\CloseCursorStatement::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class CloseCursorStatementTest extends TestCase
{
    public function testWithOriginRetainsTheCursorRequest(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CLOSE cur');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Cursor\CloseCursorStatement::class, $statement);
        self::assertSame('cur', $statement->name);
        $changed = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $changed);
        self::assertSame($statement->toString(), $changed->toString());
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Cursor\CloseCursorStatement::class, $binder->bind($changed->toString()));
    }
}
