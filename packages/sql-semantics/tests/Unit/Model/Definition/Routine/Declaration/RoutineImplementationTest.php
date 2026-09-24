<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Declaration\ReturnBody;
use SqlSemantics\Model\Definition\Routine\Declaration\RoutineImplementation;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(RoutineImplementation::class)]
#[Small]
final class RoutineImplementationTest extends TestCase
{
    public function testRetainsTheTransformTypes(): void
    {
        $implementation = new RoutineImplementation('sql', [TypeDescriptor::builtin(Dialect::PostgreSql, 'integer')], new ReturnBody(Expression::literal(1, Dialect::PostgreSql)));
        self::assertSame('integer', $implementation->transforms[0]->name);
    }

    public function testRejectsATransformOfAnotherDatabaseLanguage(): void
    {
        $this->expectException(InvalidStructure::class);
        new RoutineImplementation('sql', [TypeDescriptor::builtin(Dialect::MySql, 'integer')], new ReturnBody(Expression::literal(1, Dialect::PostgreSql)));
    }
}
