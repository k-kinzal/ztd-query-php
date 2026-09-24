<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Procedural;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Procedural\GetConditionDiagnosticsStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Procedural\Diagnostics;

#[CoversClass(Diagnostics::class)]
#[Medium]
final class DiagnosticsTest extends TestCase
{
    public function testWriteSpellsTheAreaConditionAndTargets(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GET DIAGNOSTICS CONDITION 1 @`a b` = CLASS_ORIGIN');
        self::assertInstanceOf(GetConditionDiagnosticsStatement::class, $statement);
        self::assertSame('GET CURRENT DIAGNOSTICS CONDITION 1 @`a b` = CLASS_ORIGIN', Diagnostics::write($statement)->toString());
    }
}
