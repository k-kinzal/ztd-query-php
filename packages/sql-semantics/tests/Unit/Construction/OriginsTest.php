<?php

declare(strict_types=1);

namespace Tests\Unit\Construction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Origins;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;

#[CoversClass(Origins::class)]
#[Small]
final class OriginsTest extends TestCase
{
    public function testRecordCountsBytesFromTheFirstThroughTheLastToken(): void
    {
        $origins = new Origins();
        $value = new IntegerLiteral('1');
        $source = (new Semantics(Dialect::Sqlite))->parser()->parse('  SELECT /* comment */ 1  ');
        self::assertSame($value, $origins->record($value, $source));
        self::assertSame([2, 22], [$origins->publish()->of($value)?->offset, $origins->publish()->of($value)?->length]);
    }

    public function testPublishDoesNotChangeWhenMoreOccurrencesAreRecorded(): void
    {
        $origins = new Origins();
        $published = $origins->publish();
        $origins->record(new IntegerLiteral('1'), (new Semantics(Dialect::Sqlite))->parser()->parse('SELECT 1'));
        self::assertSame([], $published->origins);
        self::assertCount(1, $origins->publish()->origins);
    }
}
