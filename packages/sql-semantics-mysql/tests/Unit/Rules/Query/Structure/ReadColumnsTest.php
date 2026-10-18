<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Structure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadColumns;
use SqlSemantics\Platform\MySql\Statement as M;

#[CoversClass(ReadColumns::class)]
#[Small]
final class ReadColumnsTest extends TestCase
{
    public function testProblemDistinguishesExistenceFromReadingColumns(): void
    {
        $query = new M\Query\Select([], [new M\Query\Star()]);
        self::assertInstanceOf(M\Query\Problem\Misuse::class, (new ReadColumns())->problem($query));
        self::assertNull((new ReadColumns())->problem($query, true));
        self::assertInstanceOf(M\Query\Problem\Misuse::class, (new ReadColumns())->problem(new M\Query\ParenthesizedQuery($query)));
    }

    public function testProblemRejectsEmptyQualifiersIndependentlyOfTheInputRelation(): void
    {
        $name = new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name(''));
        $query = new M\Query\Select([], [new M\Name\TableWildcard($name)], new M\Relation\Dual());
        self::assertInstanceOf(M\Query\Problem\Misuse::class, (new ReadColumns())->problem($query));
        self::assertNull((new ReadColumns())->problem($query, true));
    }

    public function testRowsDistinguishesDefaultsEmptyAndInconsistentWidths(): void
    {
        $rules = new ReadColumns();
        self::assertInstanceOf(M\Query\Problem\Misuse::class, $rules->rows(new M\Query\ValuesQuery([new M\Query\ValueRow([])])));
        self::assertInstanceOf(M\Dml\Problem\WriteMisuse::class, $rules->rows(new M\Query\ValuesQuery([new M\Query\ValueRow([new M\Dml\DefaultRequest()])])));
        self::assertInstanceOf(M\Query\Problem\CountMismatch::class, $rules->rows(new M\Query\ValuesQuery([new M\Query\ValueRow([new M\Literal\NumberLiteral('1')]), new M\Query\ValueRow([])])));
        self::assertNull($rules->rows(new M\Query\ValuesQuery([new M\Query\ValueRow([new M\Literal\NumberLiteral('1')])])));
    }

    public function testCombinedLeavesUnknownCatalogWidthsForResolution(): void
    {
        $one = new M\Query\ValuesQuery([new M\Query\ValueRow([new M\Literal\NumberLiteral('1')])]);
        $two = new M\Query\ValuesQuery([new M\Query\ValueRow([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')])]);
        self::assertInstanceOf(M\Query\Problem\CountMismatch::class, (new ReadColumns())->combined($one, $two));
        self::assertNull((new ReadColumns())->combined($one, $one));
        self::assertNull((new ReadColumns())->combined($one, new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'))))));
    }
}
