<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Routine\ParameterRule;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Parameter;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterMode;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\VariableDeclaration;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ParameterRule::class)]
#[Medium]
final class ParameterRuleTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerParametersLowersAProcedureListInOrder(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 5.7' => ['mysql-5.7.44'];
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerParametersLowersAProcedureListInOrder')]
    public function testParametersLowersAProcedureListInOrder(string $release): void
    {
        $create = (new Semantics(Dialect::MySql, $release))->analyze('create procedure p(in a int, out b char(3) collate latin1_bin, inout c int, d int) select 1');
        $statement = $create->statement;
        self::assertInstanceOf(CreateProcedure::class, $statement);
        self::assertSame(['a', 'b', 'c', 'd'], array_map(static fn (Parameter $parameter): string => $parameter->name->value, $statement->parameters->parameters));
        self::assertSame([ParameterMode::In, ParameterMode::Out, ParameterMode::InOut, null], array_map(static fn (Parameter $parameter): ?ParameterMode => $parameter->mode, $statement->parameters->parameters));
        self::assertSame([null, 'latin1_bin', null, null], array_map(static fn (Parameter $parameter): ?string => $parameter->collation?->name?->value, $statement->parameters->parameters));
        self::assertSame('CREATE PROCEDURE p(IN a INT, OUT b CHAR(3) COLLATE latin1_bin, INOUT c INT, d INT) SELECT 1', $create->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerParametersLowersAFunctionList(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 5.7' => ['mysql-5.7.44'];
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerParametersLowersAFunctionList')]
    public function testParametersLowersAFunctionList(string $release): void
    {
        $create = (new Semantics(Dialect::MySql, $release))->analyze('create function f(a int, b char(3) collate latin1_bin) returns int return a');
        $statement = $create->statement;
        self::assertInstanceOf(CreateFunction::class, $statement);
        self::assertSame(['a', 'b'], array_map(static fn (Parameter $parameter): string => $parameter->name->value, $statement->parameters->parameters));
        self::assertSame([null, null], array_map(static fn (Parameter $parameter): ?ParameterMode => $parameter->mode, $statement->parameters->parameters));
        self::assertSame([null, 'latin1_bin'], array_map(static fn (Parameter $parameter): ?string => $parameter->collation?->name?->value, $statement->parameters->parameters));
        self::assertSame('CREATE FUNCTION f(a INT, b CHAR(3) COLLATE latin1_bin) RETURNS INT RETURN a', $create->toString());
    }

    public function testParametersLowersAnEmptyList(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');
        $procedure = $semantics->analyze('create procedure p() select 1')->statement;
        self::assertInstanceOf(CreateProcedure::class, $procedure);
        self::assertSame([], $procedure->parameters->parameters);

        $function = $semantics->analyze('create function f() returns int return 1')->statement;
        self::assertInstanceOf(CreateFunction::class, $function);
        self::assertSame([], $function->parameters->parameters);
    }

    public function testParametersRefusesAnotherProduction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rule = new ParameterRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: sp_name: ident');

        $rule->parameters(new Node('sp_name', 1, []));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerParameterReadsTheModeNameAndType(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerParameterReadsTheModeNameAndType')]
    public function testParameterReadsTheModeNameAndType(string $release): void
    {
        $statement = (new Semantics(Dialect::MySql, $release))->analyze('CREATE PROCEDURE p(OUT total INT) SELECT 1')->statement;
        self::assertInstanceOf(CreateProcedure::class, $statement);
        self::assertEquals([new Parameter(new Name('total'), new Integral(IntegralKind::Int), null, ParameterMode::Out)], $statement->parameters->parameters);
    }

    public function testParameterRefusesAnotherProduction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rule = new ParameterRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: sp_name: ident');

        $rule->parameter(new Node('sp_name', 1, []));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerDeclaredLowersATypeWithItsCollation(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 5.7' => ['mysql-5.7.44'];
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerDeclaredLowersATypeWithItsCollation')]
    public function testDeclaredLowersATypeWithItsCollation(string $release): void
    {
        $semantics = new Semantics(Dialect::MySql, $release);
        $function = $semantics->analyze('create function f() returns char(3) collate latin1_bin return 1');
        self::assertInstanceOf(CreateFunction::class, $function->statement);
        self::assertSame('latin1_bin', $function->statement->collation?->name?->value);
        self::assertSame('CREATE FUNCTION f() RETURNS CHAR(3) COLLATE latin1_bin RETURN 1', $function->toString());

        $plain = $semantics->analyze('create function f() returns int return 1')->statement;
        self::assertInstanceOf(CreateFunction::class, $plain);
        self::assertEquals(new Integral(IntegralKind::Int), $plain->returns);
        self::assertNull($plain->collation);

        $procedure = $semantics->analyze("create procedure p() begin declare v char(3) collate latin1_bin default 'a'; end");
        self::assertInstanceOf(CreateProcedure::class, $procedure->statement);
        self::assertInstanceOf(Block::class, $procedure->statement->body);
        $declaration = $procedure->statement->body->declarations[0];
        self::assertInstanceOf(VariableDeclaration::class, $declaration);
        self::assertSame('latin1_bin', $declaration->collation?->name?->value);
        self::assertSame("CREATE PROCEDURE p() BEGIN DECLARE v CHAR(3) COLLATE latin1_bin DEFAULT 'a'; END", $procedure->toString());
    }

    public function testDeclaredRefusesAnotherProductionWithoutACollationNode(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rule = new ParameterRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: sp_name: ident');

        $rule->declared(new Node('sp_name', 1, []), null);
    }
}
