<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Characteristic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Determinism;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(Determinism::class)]
#[Medium]
final class DeterminismTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string, bool}>
     */
    public static function providerRenderWritesNotBeforeANonDeterministicRoutine(): iterable
    {
        yield 'MySQL 5.6 DETERMINISTIC' => ['mysql-5.6.51', 'create function f() returns int deterministic return 1', 'CREATE FUNCTION f() RETURNS INT DETERMINISTIC RETURN 1', true];
        yield 'MySQL 8.0 NOT DETERMINISTIC' => ['mysql-8.0.44', 'create function f() returns int not deterministic return 1', 'CREATE FUNCTION f() RETURNS INT NOT DETERMINISTIC RETURN 1', false];
        yield 'MySQL 9.1 after another characteristic' => ['mysql-9.1.0', "create function f() returns int comment 'c' deterministic return 1", "CREATE FUNCTION f() RETURNS INT COMMENT 'c' DETERMINISTIC RETURN 1", true];
    }

    #[DataProvider('providerRenderWritesNotBeforeANonDeterministicRoutine')]
    public function testRenderWritesNotBeforeANonDeterministicRoutine(string $release, string $sql, string $expected, bool $deterministic): void
    {
        $create = (new Semantics(Dialect::MySql, $release))->analyze($sql);
        $statement = $create->statement;
        self::assertInstanceOf(CreateFunction::class, $statement);
        $characteristic = $statement->characteristics[array_key_last($statement->characteristics)] ?? null;
        self::assertInstanceOf(Determinism::class, $characteristic);

        self::assertSame($deterministic, $characteristic->deterministic);
        self::assertSame($expected, $create->toString());
    }

    public function testRenderKeepsAProcedureDeclarationAndItsContradiction(): void
    {
        $sql = 'CREATE PROCEDURE p() DETERMINISTIC NOT DETERMINISTIC SELECT 1';

        self::assertSame($sql, (new Semantics(Dialect::MySql))->analyze(strtolower($sql))->toString());
    }

    public function testRenderWritesAConstructedCharacteristic(): void
    {
        $codec = new Codec((new Semantics(Dialect::MySql))->profile()->grammar);
        $deterministic = new Output($codec);
        (new Determinism(true))->render($deterministic);
        $nondeterministic = new Output($codec);
        (new Determinism(false))->render($nondeterministic);

        self::assertSame('DETERMINISTIC', (new Lexical())->join($deterministic->pieces()));
        self::assertSame('NOT DETERMINISTIC', (new Lexical())->join($nondeterministic->pieces()));
    }
}
