<?php

declare(strict_types=1);

namespace Tests\Unit\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rendering\Codec;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Codec::class)]
#[Medium]
final class CodecTest extends TestCase
{
    public function testNameWritesAPlainWordBareAtEveryPosition(): void
    {
        $codec = new Codec();

        self::assertSame('users', $codec->name(new Name('users'), NameUse::Relation));
        self::assertSame('_x1', $codec->name(new Name('_x1'), NameUse::Column));
        self::assertSame('MixedCase', $codec->name(new Name('MixedCase'), NameUse::Alias));
        self::assertSame('rowid', $codec->name(new Name('rowid'), NameUse::Column));
        self::assertSame('json_each', $codec->name(new Name('json_each'), NameUse::Routine));
    }

    public function testNameQuotesAKeywordInBackticksWithoutRegardToCase(): void
    {
        $codec = new Codec();

        self::assertSame('`select`', $codec->name(new Name('select'), NameUse::Column));
        self::assertSame('`Order`', $codec->name(new Name('Order'), NameUse::Relation));
        self::assertSame('`KEY`', $codec->name(new Name('KEY'), NameUse::Column));
        self::assertSame('`window`', $codec->name(new Name('window'), NameUse::Label));
    }

    public function testNameQuotesTheWordsTrueAndFalseBecauseSqliteReadsThemAsConstants(): void
    {
        $codec = new Codec();

        self::assertSame('`true`', $codec->name(new Name('true'), NameUse::Column));
        self::assertSame('`FALSE`', $codec->name(new Name('FALSE'), NameUse::Column));
    }

    public function testNameQuotesAWordThatIsNoBareIdentifierAndDoublesEmbeddedBackticks(): void
    {
        $codec = new Codec();

        self::assertSame('`1a`', $codec->name(new Name('1a'), NameUse::Column));
        self::assertSame('`a b`', $codec->name(new Name('a b'), NameUse::Column));
        self::assertSame('`a``b`', $codec->name(new Name('a`b'), NameUse::Column));
        self::assertSame('``', $codec->name(new Name(''), NameUse::Column));
        self::assertSame('`täble`', $codec->name(new Name('täble'), NameUse::Relation));
    }

    public function testNameSpellsTheNamesOfARenderedStatementSoTheyDecodeToTheSameNames(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = $semantics->analyze('SELECT "select" AS c1, [a b] AS c2, `x``y` AS c3 FROM "order"');

        self::assertSame('SELECT "select" AS c1, `a b` AS c2, `x``y` AS c3 FROM `order`', $operation->toString());
        self::assertSame('SELECT "select" AS c1, `a b` AS c2, `x``y` AS c3 FROM `order`', $semantics->analyze($operation->toString())->toString());
    }
}
