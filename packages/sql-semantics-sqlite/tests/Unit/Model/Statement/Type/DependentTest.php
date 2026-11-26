<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Missing\UnboundParameter;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Missing\UndeclaredRelation;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Dependent;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(Dependent::class)]
#[Medium]
final class DependentTest extends TestCase
{
    public function testMissingNamesTheDeclarationATypeWaitsFor(): void
    {
        $type = (new Inspection())->inspect('SELECT a FROM t')->field('a')->type;

        self::assertInstanceOf(Dependent::class, $type);
        self::assertCount(1, $type->missing);
        self::assertInstanceOf(UndeclaredRelation::class, $type->missing[0]);
        self::assertSame('t', $type->missing[0]->name->name->value);
    }

    public function testMissingNamesTheParameterATypeWaitsFor(): void
    {
        $type = (new Inspection())->inspect('SELECT ?1')->field(0)->type;

        self::assertInstanceOf(Dependent::class, $type);
        self::assertInstanceOf(UnboundParameter::class, $type->missing[0]);
        self::assertSame('?1', $type->missing[0]->marker);
    }

    public function testMissingIsNeverEmpty(): void
    {
        $this->expectExceptionMessage('A dependent type names at least one missing input.');

        new Dependent([]);
    }
}
