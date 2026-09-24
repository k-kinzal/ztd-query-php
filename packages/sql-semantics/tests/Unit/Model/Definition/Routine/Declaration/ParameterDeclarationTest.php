<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Declaration\ParameterDeclaration;
use SqlSemantics\Model\Definition\Routine\ParameterMode;
use SqlSemantics\Model\Definition\Routine\RoutineParameter;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(ParameterDeclaration::class)]
#[Small]
final class ParameterDeclarationTest extends TestCase
{
    public function testRejectsASetParameter(): void
    {
        $this->expectException(InvalidStructure::class);
        new ParameterDeclaration(new RoutineParameter(TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), setOf: true));
    }

    public function testRejectsADefaultOfAnotherDatabaseLanguage(): void
    {
        $this->expectException(InvalidStructure::class);
        new ParameterDeclaration(new RoutineParameter(TypeDescriptor::builtin(Dialect::PostgreSql, 'integer')), Expression::literal(1, Dialect::MySql));
    }

    #[TestWith([ParameterMode::Implicit, true, false])]
    #[TestWith([ParameterMode::Input, true, false])]
    #[TestWith([ParameterMode::Variadic, true, false])]
    #[TestWith([ParameterMode::InputOutput, true, true])]
    #[TestWith([ParameterMode::Output, false, true])]
    public function testInputFollowsTheMode(ParameterMode $mode, bool $input, bool $output): void
    {
        $declaration = new ParameterDeclaration(new RoutineParameter(TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), $mode));
        self::assertSame($input, $declaration->input());
        self::assertNotSame($output, $declaration->output() === false);
    }

    #[TestWith([ParameterMode::Input, false])]
    #[TestWith([ParameterMode::InputOutput, true])]
    #[TestWith([ParameterMode::Output, true])]
    public function testOutputFollowsTheMode(ParameterMode $mode, bool $output): void
    {
        self::assertSame($output, (new ParameterDeclaration(new RoutineParameter(TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), $mode)))->output());
    }
}
