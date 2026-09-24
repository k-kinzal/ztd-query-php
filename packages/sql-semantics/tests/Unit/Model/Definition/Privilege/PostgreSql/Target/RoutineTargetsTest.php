<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Privilege\PostgreSql\Target;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\RoutineClass;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\RoutineTargets;
use SqlSemantics\Model\Definition\Routine\RoutineByName;
use SqlSemantics\Model\Definition\Routine\RoutineBySignature;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantPrivilegesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RoutineTargets::class)]
#[Medium]
final class RoutineTargetsTest extends TestCase
{
    public function testRetainsNamedAndSignedRoutinesInRequestOrder(): void
    {
        $named = new RoutineByName(new QualifiedName(['f']));
        $signed = new RoutineBySignature(new QualifiedName(['app', 'g']), []);
        $targets = new RoutineTargets(RoutineClass::Procedure, [$named, $signed]);
        self::assertSame(RoutineClass::Procedure, $targets->class);
        self::assertSame([$named, $signed], $targets->routines);
        self::assertSame(['f'], $targets->routines[0]->name->parts);
    }

    public function testReadsTheRoutinesOfABoundGrant(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('GRANT EXECUTE ON FUNCTION f, g(), app.h(IN id integer) TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertInstanceOf(RoutineTargets::class, $statement->target);
        self::assertSame(RoutineClass::Function, $statement->target->class);
        self::assertCount(3, $statement->target->routines);
        self::assertInstanceOf(RoutineByName::class, $statement->target->routines[0]);
        self::assertInstanceOf(RoutineBySignature::class, $statement->target->routines[1]);
        self::assertSame([], $statement->target->routines[1]->parameters);
        self::assertInstanceOf(RoutineBySignature::class, $statement->target->routines[2]);
        self::assertSame(['app', 'h'], $statement->target->routines[2]->name->parts);
        self::assertCount(1, $statement->target->routines[2]->parameters);
        self::assertSame('GRANT EXECUTE ON FUNCTION "f", "g"(), "app"."h"(IN "id" integer) TO "a"', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }
}
