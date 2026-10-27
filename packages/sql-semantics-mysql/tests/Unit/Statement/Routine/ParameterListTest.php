<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Parameter;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterMode;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(ParameterList::class)]
#[Medium]
final class ParameterListTest extends TestCase
{
    public function testDeriveRelationAnswersOneNullablePositionPerParameter(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-9.1.0', null, ParameterStyle::Native), null, [], false));
        $fact = $derivation->relation(new ParameterList([
            new Parameter(new Name('x'), new Integral(IntegralKind::Int), null, ParameterMode::Out),
            new Parameter(new Name('y'), new Character(CharacterKind::VarChar, '3')),
        ]), $derivation->environment());

        self::assertEquals(new RowShape([
            new OutputSlot(new Name('x'), new Known(new Integral(IntegralKind::Int)), Nullability::Nullable),
            new OutputSlot(new Name('y'), new Known(new Character(CharacterKind::VarChar, '3')), Nullability::Nullable),
        ]), $fact->shape);
        self::assertNull($fact->table);
    }

    public function testDeriveRelationAnswersTheRowOfAnAnalyzedRoutine(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p(INOUT x INT, y BIGINT) SELECT x');
        $statement = $create;
        self::assertInstanceOf(CreateProcedure::class, $statement);

        self::assertNull((new \SqlSemantics\Validation\Equivalence())->difference(new RowShape([new OutputSlot(new Name('x'), new Known(new Integral(IntegralKind::Int)), Nullability::Nullable), new OutputSlot(new Name('y'), new Known(new Integral(IntegralKind::BigInt)), Nullability::Nullable)]), $statement->parameters->relationInformation()->shape));
    }

    public function testDeriveRelationResolvesEveryDirectionInTheBody(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p(IN a INT, OUT b INT, INOUT c INT, d INT) SELECT a, b, c, d, z', []);

        self::assertSame(['Column z does not exist.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $create->diagnostics()));
    }

    public function testDeriveRelationLetsALocalVariableHideAParameter(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p(x INT) BEGIN DECLARE x BIGINT; SELECT x; END', [])->diagnostics());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerRenderWritesTheParentheses(): iterable
    {
        yield 'MySQL 5.6 empty' => ['mysql-5.6.51', 'create procedure p() select 1', 'CREATE PROCEDURE p() SELECT 1'];
        yield 'MySQL 5.7 two parameters' => ['mysql-5.7.44', 'create procedure p(a int,b int) select 1', 'CREATE PROCEDURE p(a INT, b INT) SELECT 1'];
        yield 'MySQL 8.0 function' => ['mysql-8.0.44', 'create function f(a int, b char(2)) returns int return 1', 'CREATE FUNCTION f(a INT, b CHAR(2)) RETURNS INT RETURN 1'];
    }

    #[DataProvider('providerRenderWritesTheParentheses')]
    public function testRenderWritesTheParentheses(string $release, string $sql, string $expected): void
    {
        self::assertSame($expected, (new Semantics(Dialect::MySql, $release))->analyze($sql)->toString());
    }

    public function testRenderWritesAConstructedList(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $empty = new Output(new Codec($semantics->profile()->grammar));
        (new ParameterList([]))->render($empty);
        $two = new Output(new Codec($semantics->profile()->grammar));
        (new ParameterList([new Parameter(new Name('a'), new Integral(IntegralKind::Int)), new Parameter(new Name('b'), new Integral(IntegralKind::Int), null, ParameterMode::Out)]))->render($two);

        self::assertSame('()', (new Lexical())->join($empty->pieces()));
        self::assertSame('(a INT, OUT b INT)', (new Lexical())->join($two->pieces()));
    }
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerRepeatedNames(): iterable
    {
        yield 'identical' => ['x', 'x'];
        yield 'ASCII case' => ['x', 'X'];
        yield 'Unicode case' => ['é', 'É'];
        yield 'accent' => ['é', 'e'];
    }

    #[DataProvider('providerRepeatedNames')]
    public function testParametersHaveDistinctIdentities(string $first, string $second): void
    {
        $this->expectExceptionMessage('A routine parameter list has distinct case- and accent-insensitive names.');

        new ParameterList([new Parameter(new Name($first), new Integral(IntegralKind::Int)), new Parameter(new Name($second), new Integral(IntegralKind::Int))]);
    }

    public function testLegacyNamesCannotAccompanyModernQuerySyntax(): void
    {
        $parameters = new ParameterList([new Parameter(new Name(''), new Integral(IntegralKind::Int))]);
        $body = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery([new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')])]));
        $this->expectExceptionMessage('A query cannot combine features removed before MySQL 8.0 with features introduced in MySQL 8.0 or later.');

        new CreateProcedure(new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('p')), $parameters, $body);
    }

    public function testDeriveRelationReportsANameWhenTheSessionUsesAModernRelease(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $derivation = new Derivation($semantics->context([]));
        $parameters = new ParameterList([new Parameter(new Name('x '), new Integral(IntegralKind::Int))]);

        $derivation->relation($parameters, $derivation->environment());

        self::assertSame(["Incorrect routine name 'x '"], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $derivation->facts()->diagnostics));
    }

}
