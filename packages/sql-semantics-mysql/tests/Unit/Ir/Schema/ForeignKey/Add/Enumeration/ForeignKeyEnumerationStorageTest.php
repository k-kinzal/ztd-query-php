<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add\Enumeration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyTextCollation;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Enumeration\ForeignKeyEnumerationKind as Kind;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Enumeration\ForeignKeyEnumerationMember;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Enumeration\ForeignKeyEnumerationStorage;

#[CoversClass(ForeignKeyEnumerationStorage::class)]
final class ForeignKeyEnumerationStorageTest extends TestCase
{
    #[DataProvider('providerWidths')]
    public function testKeyBytesCountsDeclaredSlotsEvenWhenTheLabelsRepeat(Kind $kind, int $count, int $width): void
    {
        $member = new ForeignKeyEnumerationMember('label');
        $collation = ForeignKeyTextCollation::Binary;
        $storage = new ForeignKeyEnumerationStorage($kind, array_fill(0, $count, $member), $collation);

        self::assertCount($count, $storage->members);
        self::assertSame($width, $storage->keyBytes());
    }

    /**
     * @return iterable<string, array{Kind, int, int}>
     */
    public static function providerWidths(): iterable
    {
        yield 'last one-byte ENUM' => [Kind::Enum, 255, 1];
        yield 'first two-byte ENUM' => [Kind::Enum, 256, 2];
        yield 'last one-byte SET' => [Kind::Set, 8, 1];
        yield 'first two-byte SET' => [Kind::Set, 9, 2];
        yield 'first three-byte SET' => [Kind::Set, 17, 3];
        yield 'first four-byte SET' => [Kind::Set, 25, 4];
        yield 'first eight-byte SET' => [Kind::Set, 33, 8];
        yield 'SET with 67 repeated labels' => [Kind::Set, 67, 8];
    }

    public function testCheckReleaseAllowsTheDistinctDictionaryRepresentations(): void
    {
        $legacy = new ForeignKeyEnumerationMember('space ');
        $modern = new ForeignKeyEnumerationMember("fixed\0data");
        $collation = ForeignKeyTextCollation::Unicode;
        (new ForeignKeyEnumerationStorage(Kind::Set, [$legacy], $collation))->checkRelease(true);
        (new ForeignKeyEnumerationStorage(Kind::Set, [$modern], $collation))->checkRelease(false);

        self::assertSame(['space ', "fixed\0data"], [$legacy->value, $modern->value]);
    }
}
