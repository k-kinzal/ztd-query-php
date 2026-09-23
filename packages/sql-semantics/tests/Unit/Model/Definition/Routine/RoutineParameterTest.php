<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine;

#[CoversClass(Routine\RoutineParameter::class)]
final class RoutineParameterTest extends TestCase
{
    public function testArgumentRetainsItsDeclaredTypeAndDirection(): void
    {
        $type = \SqlSemantics\Type\TypeDescriptor::builtin(Dialect::PostgreSql, 'integer');
        $argument = new Routine\RoutineParameter($type, Routine\ParameterMode::Input, 'amount');
        self::assertSame($type, $argument->type);
        self::assertSame('amount', $argument->name);
        self::assertSame(Routine\ParameterMode::Input, $argument->mode);
        self::assertFalse($argument->setOf);
    }

    public function testArgumentRejectsTypesFromAnotherDatabaseLanguage(): void
    {
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new Routine\RoutineParameter(\SqlSemantics\Type\TypeDescriptor::builtin(Dialect::MySql, 'integer'));
    }

    public function testArgumentRejectsAnInferredValueCategory(): void
    {
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new Routine\RoutineParameter(\SqlSemantics\Type\TypeDescriptor::builtin(Dialect::PostgreSql, 'unknown'));
    }

}
