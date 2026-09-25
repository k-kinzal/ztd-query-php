<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Privilege\PostgreSql\Target;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\RoutineClass;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\RoutineTargets;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantPrivilegesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RoutineClass::class)]
#[Medium]
final class RoutineClassTest extends TestCase
{
    public function testRepresentsEveryRoutineLookupClass(): void
    {
        self::assertSame(['FUNCTION', 'PROCEDURE', 'ROUTINE'], array_column(RoutineClass::cases(), 'value'));
    }

    #[TestWith(['FUNCTION', RoutineClass::Function])]
    #[TestWith(['PROCEDURE', RoutineClass::Procedure])]
    #[TestWith(['ROUTINE', RoutineClass::Routine])]
    public function testBindsTheDeclaredClassAndWritesItBack(string $word, RoutineClass $class): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('GRANT EXECUTE ON ' . $word . ' f TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertInstanceOf(RoutineTargets::class, $statement->target);
        self::assertSame($class, $statement->target->class);
        self::assertSame('GRANT EXECUTE ON ' . $word . ' "f" TO "a"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }
}
