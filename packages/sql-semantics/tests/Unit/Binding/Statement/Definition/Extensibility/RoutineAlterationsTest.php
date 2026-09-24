<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Extensibility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\Extensibility\RoutineAlterations;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Catalog\Kind\RoutineKind;
use SqlSemantics\Model\Definition\Routine\RoutineBySignature;
use SqlSemantics\Model\Statement\Definition\Routine\AlterRoutineStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RoutineAlterations::class)]
#[Medium]
final class RoutineAlterationsTest extends TestCase
{
    #[TestWith(['ALTER FUNCTION f(integer) STABLE', RoutineKind::Function])]
    #[TestWith(['ALTER PROCEDURE f(integer) SECURITY INVOKER RESTRICT', RoutineKind::Procedure])]
    #[TestWith(['ALTER ROUTINE f(integer) COST 3', RoutineKind::Routine])]
    public function testAlterReadsTheObjectClassAndSignature(string $sql, RoutineKind $kind): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(AlterRoutineStatement::class, $statement);
        self::assertSame($kind, $statement->routine);
        self::assertInstanceOf(RoutineBySignature::class, $statement->target);
    }

    #[TestWith(['ALTER PROCEDURE p COST 3'])]
    #[TestWith(['ALTER FUNCTION f STABLE VOLATILE'])]
    public function testAlterRejectsImpossibleChanges(string $sql): void
    {
        try {
            (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
            self::fail('The change must be diagnosed.');
        } catch (InvalidSql $error) {
            self::assertSame(InputViolation::RoutineAttribute, $error->violation);
        }
    }
}
