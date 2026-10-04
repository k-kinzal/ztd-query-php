<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\ItemSpelling;
use SqlSemantics\Statement\Type\Dependent;

#[CoversClass(ItemSpelling::class)]
#[Medium]
final class ItemSpellingTest extends TestCase
{
    public function testDescribeNamesTheSourceText(): void
    {
        self::assertSame('the source text MySQL names an unaliased select list expression after', (new ItemSpelling())->describe());
    }

    public function testADerivedColumnWithoutFixedNameIsNotFoundByName(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT x FROM (SELECT 1 + 1) AS d', []);

        self::assertInstanceOf(Dependent::class, $operation->field('x')->type);
        self::assertSame([], $operation->facts->diagnostics);
        self::assertNotNull((new Semantics(Dialect::MySql))->analyze('SELECT * FROM (SELECT 1 + 1) AS d', [])->fields());
    }
}
