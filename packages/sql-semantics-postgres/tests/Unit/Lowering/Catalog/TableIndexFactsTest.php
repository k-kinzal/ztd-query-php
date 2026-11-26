<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Lowering\Catalog\TableIndexFacts;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Facade\StatementBinder;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Semantics;
use SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable;

#[CoversClass(TableIndexFacts::class)]
#[Medium]
final class TableIndexFactsTest extends TestCase
{
    public function testCollectKeepsASingleExplicitConstraintName(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context([]);
        self::assertInstanceOf(AnalysisContext::class, $context);
        $binder = new StatementBinder(Platform::shared()->parser($semantics->profile()));
        $statement = $binder->resolve('CREATE TABLE items (id integer, CONSTRAINT lookup UNIQUE(id))', $context)->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        [$name, $opaque] = (new TableIndexFacts())->collect($statement);

        self::assertSame('lookup', $name?->value);
        self::assertFalse($opaque);
    }
}
