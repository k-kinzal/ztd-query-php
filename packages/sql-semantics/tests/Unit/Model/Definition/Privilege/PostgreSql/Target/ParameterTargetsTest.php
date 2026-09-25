<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Privilege\PostgreSql\Target;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\ParameterTargets;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantPrivilegesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ParameterTargets::class)]
#[Medium]
final class ParameterTargetsTest extends TestCase
{
    public function testRetainsDottedParameterNamesInRequestOrder(): void
    {
        $custom = new QualifiedName(['app', 'max_rows']);
        $plain = new QualifiedName(['work_mem']);
        $targets = new ParameterTargets([$custom, $plain]);
        self::assertSame([$custom, $plain], $targets->parameters);
        self::assertSame(['app', 'max_rows'], $targets->parameters[0]->parts);
    }

    public function testReadsTheParametersOfABoundGrant(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('GRANT SET ON PARAMETER work_mem, app.max_rows TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertEquals(new ParameterTargets([new QualifiedName(['work_mem']), new QualifiedName(['app', 'max_rows'])]), $statement->target);
        self::assertSame('GRANT SET ON PARAMETER "work_mem", "app"."max_rows" TO "a"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }
}
