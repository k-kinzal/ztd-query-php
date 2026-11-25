<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\ColumnKind;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexPartsLowering;
use SqlSemantics\Platform\MySql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;

#[CoversClass(IndexPartsLowering::class)]
#[Medium]
final class IndexPartsLoweringTest extends TestCase
{
    public function testResolvePinsKeyOccurrencesToTheSelectedDeclaration(): void
    {
        $context = (new Semantics())->context(['CREATE TABLE app.events (a INT NULL, extra BIGINT, b INT NOT NULL)']);
        self::assertInstanceOf(AnalysisContext::class, $context);
        [$heading, $types, $parts] = (new IndexPartsLowering())->resolve(
            $context->tables[0],
            [new ColumnPart(new Name('B'), direction: Direction::Descending), new ColumnPart(new Name('a'))],
            Comparison::AsciiInsensitive,
            false
        );

        self::assertSame(['a', 'b'], array_column($heading, 'value'));
        self::assertSame([ColumnKind::SignedInt, ColumnKind::RequiredSignedInt], $types);
        self::assertSame([1, 0], array_column($parts, 'slot'));
        self::assertSame([true, false], array_column($parts, 'descending'));
    }
}
