<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Engine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Inspection\Engine\EngineSelection;
use SqlSemantics\Model\Statement\Inspection\Server\ShowEngineReportStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(EngineSelection::class)]
#[Medium]
final class EngineSelectionTest extends TestCase
{
    public function testDistinguishesEveryEngineFromAnEngineNamedAll(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $all = $binder->bind('SHOW ENGINE ALL MUTEX');
        $named = $binder->bind("SHOW ENGINE 'all' MUTEX");
        self::assertInstanceOf(ShowEngineReportStatement::class, $all);
        self::assertInstanceOf(ShowEngineReportStatement::class, $named);
        self::assertSame(EngineSelection::All, $all->engine);
        self::assertSame('all', $named->engine);
        self::assertSame('SHOW ENGINE ALL MUTEX', $all->toString());
        self::assertSame('SHOW ENGINE `all` MUTEX', $named->toString());
    }
}
