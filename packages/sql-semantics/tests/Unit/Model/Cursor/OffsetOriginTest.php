<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Cursor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Cursor\OffsetOrigin;
use SqlSemantics\Model\Cursor\PositionedRow;
use SqlSemantics\Model\Statement\Cursor\FetchCursorStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(OffsetOrigin::class)]
#[Medium]
final class OffsetOriginTest extends TestCase
{
    public function testRepresentsBothPositionOrigins(): void
    {
        self::assertSame(['ABSOLUTE', 'RELATIVE'], array_column(OffsetOrigin::cases(), 'value'));
    }

    #[TestWith(['FETCH ABSOLUTE 3 FROM cur', OffsetOrigin::Absolute])]
    #[TestWith(['FETCH RELATIVE -1 FROM cur', OffsetOrigin::Relative])]
    public function testClassifiesTheReferencePointOfAPosition(string $sql, OffsetOrigin $origin): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(FetchCursorStatement::class, $statement);
        self::assertInstanceOf(PositionedRow::class, $statement->movement);
        self::assertSame($origin, $statement->movement->origin);
    }
}
