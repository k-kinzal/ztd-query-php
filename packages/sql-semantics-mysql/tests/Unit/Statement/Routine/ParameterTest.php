<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Parameter;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterMode;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Parameter::class)]
#[Medium]
final class ParameterTest extends TestCase
{
    public function testRenderKeepsTheDirectionTheNameTheTypeAndTheCollation(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p(a INT, OUT b VARCHAR(3) COLLATE utf8mb4_bin) SELECT 1')->statement;
        self::assertInstanceOf(CreateProcedure::class, $statement);

        self::assertEquals([
            new Parameter(new Name('a'), new Integral(IntegralKind::Int)),
            new Parameter(new Name('b'), new Character(CharacterKind::VarChar, '3'), new CollationName(new Name('utf8mb4_bin')), ParameterMode::Out),
        ], $statement->parameters->parameters);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerRenderWritesTheParameter(): iterable
    {
        yield 'MySQL 5.6 character set' => [
            'mysql-5.6.51',
            'create procedure p(a int, inout b char(3) charset latin1) select a',
            'CREATE PROCEDURE p(a INT, INOUT b CHAR(3) CHARSET latin1) SELECT a',
        ];
        yield 'MySQL 5.7 every direction' => [
            'mysql-5.7.44',
            'create procedure p(in a int, out b bigint, inout c int) select 1',
            'CREATE PROCEDURE p(IN a INT, OUT b BIGINT, INOUT c INT) SELECT 1',
        ];
        yield 'MySQL 8.0 COLLATE' => ['mysql-8.0.44', 'create procedure p(out a int collate utf8mb4_bin) select 1', 'CREATE PROCEDURE p(OUT a INT COLLATE utf8mb4_bin) SELECT 1'];
        yield 'MySQL 9.1 function parameter' => ['mysql-9.1.0', 'create function f(a decimal(10, 2)) returns int return 1', 'CREATE FUNCTION f(a DECIMAL(10, 2)) RETURNS INT RETURN 1'];
    }

    #[DataProvider('providerRenderWritesTheParameter')]
    public function testRenderWritesTheParameter(string $release, string $sql, string $expected): void
    {
        self::assertSame($expected, (new Semantics(Dialect::MySql, $release))->analyze($sql)->toString());
    }

    public function testRenderWritesAConstructedParameter(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $plain = new Output(new Codec($semantics->profile()->grammar));
        (new Parameter(new Name('a'), new Integral(IntegralKind::BigInt)))->render($plain);
        $full = new Output(new Codec($semantics->profile()->grammar));
        (new Parameter(new Name('b'), new Character(CharacterKind::VarChar, '3'), new CollationName(new Name('utf8mb4_bin')), ParameterMode::InOut))->render($full);

        self::assertSame('a BIGINT', (new Lexical())->join($plain->pieces()));
        self::assertSame('INOUT b VARCHAR(3) COLLATE utf8mb4_bin', (new Lexical())->join($full->pieces()));
    }
}
