<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Definition\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Rules\Definition\Index\ConstraintIndexes;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Collate;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(ConstraintIndexes::class)]
#[Small]
final class ConstraintIndexesTest extends TestCase
{
    public function testLayoutCombinesEquivalentConstraintKeysInDeclarationOrder(): void
    {
        $table = (new Inspection())->inspect('CREATE TABLE members (id INTEGER PRIMARY KEY, code UNIQUE COLLATE NOCASE, UNIQUE(code DESC), UNIQUE(code COLLATE BINARY))')->statement;
        self::assertInstanceOf(CreateTable::class, $table);

        self::assertSame(2, (new ConstraintIndexes())->layout($table)?->count);
    }

    public function testLayoutKeepsTheNativeOrdinalOfReusedPrimaryStorage(): void
    {
        $table = (new Inspection())->inspect('CREATE TABLE members (a INT, b TEXT, UNIQUE(b), UNIQUE(a), PRIMARY KEY(a DESC)) WITHOUT ROWID')->statement;
        self::assertInstanceOf(CreateTable::class, $table);
        $layout = (new ConstraintIndexes())->layout($table);

        self::assertNotNull($layout);
        self::assertSame(2, $layout->count);
        self::assertSame(2, $layout->primaryStorage);
    }

    public function testStorageLayoutUsesTheFirstRegisteredEquivalentSignature(): void
    {
        $rules = new ConstraintIndexes();

        self::assertSame(2, $rules->storageLayout(['a', 'b'], true, 'b')?->primaryStorage);
        self::assertNull($rules->storageLayout(['a', 'b'], true, 'd'));
        self::assertNull($rules->storageLayout(['a', 'b'], false, 'b')?->primaryStorage);
    }

    public function testInlineKeysKeepTheOwningSlotAndFinalCollation(): void
    {
        $table = (new Inspection())->inspect('CREATE TABLE members (id INTEGER PRIMARY KEY, code UNIQUE COLLATE NOCASE)')->statement;
        self::assertInstanceOf(CreateTable::class, $table);

        self::assertSame(['1:6:nocase' => true], (new ConstraintIndexes())->inlineKeys($table, ['binary', 'nocase'], 0));
    }

    public function testTermUsesTheOutermostCollationOnAResolvedSlot(): void
    {
        $key = new Grouped(new Collate(
            new Collate(new ColumnUse(new Name('label')), new Name('nocase')),
            new Name('RTRIM'),
        ));

        self::assertSame([0, 'rtrim'], (new ConstraintIndexes())->term($key, ['label' => 0], ['binary']));
    }

    public function testTermCannotResolveAnUnknownColumn(): void
    {
        self::assertNull((new ConstraintIndexes())->term(new ColumnUse(new Name('missing')), ['label' => 0], ['binary']));
    }

    public function testSignatureDistinguishesANameFromADelimitedSequence(): void
    {
        $rules = new ConstraintIndexes();

        self::assertNotSame($rules->signature([[1, 'x3:1:y']]), $rules->signature([[1, 'x'], [3, 'y']]));
    }
}
