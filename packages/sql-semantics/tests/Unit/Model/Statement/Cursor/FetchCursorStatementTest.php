<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Cursor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Model\Statement\Cursor\FetchCursorStatement::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class FetchCursorStatementTest extends TestCase
{
    public function testWithOriginRetainsTheCursorRequest(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('FETCH BACKWARD ALL FROM cur');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Cursor\FetchCursorStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Cursor\RemainingRows::class, $statement->movement);
        self::assertSame(\SqlSemantics\Model\Cursor\ScanDirection::Backward, $statement->movement->direction);
        $changed = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $changed);
        self::assertSame($statement->toString(), $changed->toString());
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Cursor\FetchCursorStatement::class, $binder->bind($changed->toString()));
    }
}
