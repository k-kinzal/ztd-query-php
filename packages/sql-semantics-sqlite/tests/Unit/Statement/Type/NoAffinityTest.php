<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Type\NoAffinity;

#[CoversClass(NoAffinity::class)]
#[Medium]
final class NoAffinityTest extends TestCase
{
    public function testNameIsNoDeclaredType(): void
    {
        self::assertSame('', (new NoAffinity())->name());
    }

    public function testNameOfAViewColumnWithoutAffinityStaysEmptyThroughAFurtherView(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE s (n)');
        $view = $semantics->analyze('CREATE VIEW v AS SELECT 1 AS one, n FROM s', [$table]);
        $further = $semantics->analyze('CREATE VIEW w AS SELECT one, n FROM v', [$view]);

        self::assertInstanceOf(NoAffinity::class, $view->declarations()[0]->columns[0]->type);
        self::assertInstanceOf(NoAffinity::class, $further->declarations()[0]->columns[0]->type);
        self::assertSame('BLOB', $view->declarations()[0]->columns[1]->type->name());
        self::assertSame('BLOB', $further->declarations()[0]->columns[1]->type->name());
    }
}
