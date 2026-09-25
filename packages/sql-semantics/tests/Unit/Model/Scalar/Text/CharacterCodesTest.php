<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Text\CharacterCodes;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SimpleSerializer;
use SqlSemantics\Type\Nullability;

#[CoversClass(CharacterCodes::class)]
#[Medium]
final class CharacterCodesTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.1.0'])]
    #[TestWith(['mysql-8.2.0'])]
    #[TestWith(['mysql-8.3.0'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.0.1'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testInputsAreTheCodesKeptWithTheCharacterSet(string $release): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $release))->build('CREATE TABLE t(a INT)'));
        $query = $binder->bind('SELECT CHAR(a, 66 USING utf8mb4), CHAR(65) FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $codes = $query->outputs[0]->expression;
        self::assertInstanceOf(CharacterCodes::class, $codes);
        self::assertSame($codes->codes, $codes->inputs());
        self::assertSame('utf8mb4', $codes->characterSet);
        self::assertSame(Nullability::NotNull, $codes->nullability);
        $plain = $query->outputs[1]->expression;
        self::assertInstanceOf(CharacterCodes::class, $plain);
        self::assertNull($plain->characterSet);
        self::assertSame('longblob', $plain->type->name);
        $written = (new SimpleSerializer())->serialize($query);
        self::assertSame('SELECT CHAR(`a`, 66 USING `utf8mb4`), CHAR(65) FROM `t`', $written);
        self::assertSame($written, (new SimpleSerializer())->serialize($binder->bind($written)));
    }

    public function testSpellingNamesTheFunction(): void
    {
        $code = Expression::literal(65, Dialect::MySql);
        self::assertSame('CHAR', (new CharacterCodes($code->source, [$code]))->spelling());
    }

    public function testWithFactsKeepsTheCodes(): void
    {
        $code = Expression::literal(65, Dialect::MySql);
        $codes = new CharacterCodes($code->source, [$code], 'latin1');
        $copy = $codes->withFacts($codes->facts);
        self::assertNotSame($codes, $copy);
        self::assertSame([[$code], 'latin1'], [$copy->codes, $copy->characterSet]);
    }

    public function testWithFactsRejectsOtherFacts(): void
    {
        $code = Expression::literal(65, Dialect::MySql);
        $this->expectException(InvalidStructure::class);
        (new CharacterCodes($code->source, [$code]))->withFacts($code->facts);
    }

    public function testInputsRejectAnEmptyCodeList(): void
    {
        $code = Expression::literal(65, Dialect::MySql);
        $this->expectException(InvalidStructure::class);
        new CharacterCodes($code->source, []);
    }

    public function testInputsRejectOtherDialects(): void
    {
        $code = Expression::literal(65, Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new CharacterCodes($code->source, [$code]);
    }
}
