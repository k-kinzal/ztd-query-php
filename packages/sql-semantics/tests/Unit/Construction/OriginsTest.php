<?php

declare(strict_types=1);

namespace Tests\Unit\Construction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
    public function testNoticeRecordsTheReadBoundaryWithoutChangingAnEarlierSnapshot(): void
    {
        $origins = new Origins();
        $before = $origins->publish();
        $name = new \SqlSemantics\Statement\Identifier\Name('full');
        $warning = new \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::UnquotedFull);
        $tree = (new Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-8.4.7'))->parser()->parse('SELECT 1 AS full');
        $origins->notice($name, $warning, $tree);
        $notice = $origins->publish()->notices[0];

        self::assertSame([], $before->notices);
        self::assertSame([$name, $warning, 16], [$notice->subject, $notice->warning, $notice->offset]);
    }

    /**
     * @return iterable<array{list<\SqlParser\Parser\Node>}>
     */
    public static function providerSourcePrefixes(): iterable
    {
        $nodes = [];
        $previous = new \SqlParser\Parser\Node('empty', 0, []);
        for ($index = 0; $index < 1000; $index++) {
            $previous = new \SqlParser\Parser\Node('prefix', 0, [$previous, new \SqlParser\Lexer\Token(1, 'NUM', '1', $index * 2)]);
            $nodes[] = $previous;
        }
        yield [$nodes];
    }

    /**
     * @param list<\SqlParser\Parser\Node> $sources
     */
    #[DataProvider('providerSourcePrefixes')]
    public function testRecordHandlesAllRangesOfDeepSourcePrefixes(array $sources): void
    {
        $origins = new Origins();
        $values = array_map(static fn (\SqlParser\Parser\Node $source): \SqlSemantics\Statement\Node => $origins->record(new IntegerLiteral('1'), $source), $sources);
        $published = $origins->publish();

        self::assertCount(1000, $published->origins);
        self::assertSame([0, 1], [$published->of($values[0])?->offset, $published->of($values[0])?->length]);
        self::assertSame([0, 1999], [$published->of($values[999])?->offset, $published->of($values[999])?->length]);
    }
}
