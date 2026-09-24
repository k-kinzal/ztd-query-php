<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Declaration;
use SqlSemantics\Model\Statement\Definition\Routine\CreateFunctionStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Declaration\RoutineBody::class)]
#[Medium]
final class RoutineBodyTest extends TestCase
{
    /**
     * @param class-string<object> $class
     */
    #[TestWith(["CREATE FUNCTION f() RETURNS integer LANGUAGE sql AS 'SELECT 1'", Declaration\DefinitionBody::class])]
    #[TestWith(["CREATE FUNCTION f() RETURNS integer LANGUAGE c AS 'lib', 'sym'", Declaration\LinkedBody::class])]
    #[TestWith(['CREATE FUNCTION f() RETURNS integer RETURN 1', Declaration\ReturnBody::class])]
    #[TestWith(['CREATE FUNCTION f() RETURNS integer BEGIN ATOMIC SELECT 1; END', Declaration\AtomicBody::class])]
    public function testEachBodyFormHasItsOwnShape(string $sql, string $class): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(CreateFunctionStatement::class, $statement);
        self::assertInstanceOf($class, $statement->implementation->body);
    }
}
