<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Procedural;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Condition\SqlState;
use SqlSemantics\Model\Statement\Procedural\ResignalStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Procedural\Conditions;

#[CoversClass(Conditions::class)]
#[Medium]
final class ConditionsTest extends TestCase
{
    public function testWriteOmitsAnAbsentStateAndEmptyItems(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('RESIGNAL');
        self::assertInstanceOf(ResignalStatement::class, $statement);
        self::assertSame('RESIGNAL', Conditions::write($statement)->toString());
    }

    public function testStateSpellsTheCodeAsAString(): void
    {
        self::assertSame("SQLSTATE '22012'", Conditions::state(new SqlState('22012'))->toString());
    }
}
