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
use SqlSemantics\Platform\MySql\Statement\Routine\AlterRoutine;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\RoutineLanguage;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(RoutineLanguage::class)]
#[Medium]
final class RoutineLanguageTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerRenderWritesTheKeywordSql(): iterable
    {
        yield 'MySQL 5.6' => ['mysql-5.6.51'];
        yield 'MySQL 8.0' => ['mysql-8.0.44'];
        yield 'MySQL 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerRenderWritesTheKeywordSql')]
    public function testRenderWritesTheKeywordSql(string $release): void
    {
        $alter = (new Semantics(Dialect::MySql, $release))->analyze('alter procedure p language sql');
        $statement = $alter->statement;
        self::assertInstanceOf(AlterRoutine::class, $statement);
        $characteristic = $statement->characteristics[0] ?? null;
        self::assertInstanceOf(RoutineLanguage::class, $characteristic);

        self::assertNull($characteristic->external);
        self::assertSame('ALTER PROCEDURE p LANGUAGE SQL', $alter->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerRenderWritesTheNameOfAnExternalLanguage(): iterable
    {
        yield 'MySQL 9.0' => ['mysql-9.0.1'];
        yield 'MySQL 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerRenderWritesTheNameOfAnExternalLanguage')]
    public function testRenderWritesTheNameOfAnExternalLanguage(string $release): void
    {
        $create = (new Semantics(Dialect::MySql, $release))->analyze('create function f() returns int language javascript as $$ return 1 $$');
        $statement = $create->statement;
        self::assertInstanceOf(CreateFunction::class, $statement);
        $characteristic = $statement->characteristics[0] ?? null;
        self::assertInstanceOf(RoutineLanguage::class, $characteristic);

        self::assertSame('javascript', $characteristic->external?->value);
        self::assertSame('CREATE FUNCTION f() RETURNS INT LANGUAGE javascript AS $$ return 1 $$', $create->toString());
    }

    public function testRenderQuotesALanguageNameThatNeedsQuoting(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze("create procedure p() language `java script` as 'x'");
        $statement = $create->statement;
        self::assertInstanceOf(CreateProcedure::class, $statement);
        $characteristic = $statement->characteristics[0] ?? null;
        self::assertInstanceOf(RoutineLanguage::class, $characteristic);

        self::assertSame('java script', $characteristic->external?->value);
        self::assertSame("CREATE PROCEDURE p() LANGUAGE `java script` AS 'x'", $create->toString());
    }

    public function testRenderWritesAConstructedCharacteristic(): void
    {
        $codec = new Codec((new Semantics(Dialect::MySql))->profile()->grammar);
        $sql = new Output($codec);
        (new RoutineLanguage())->render($sql);
        $external = new Output($codec);
        (new RoutineLanguage(new Name('JAVASCRIPT')))->render($external);

        self::assertSame('LANGUAGE SQL', (new Lexical())->join($sql->pieces()));
        self::assertSame('LANGUAGE JAVASCRIPT', (new Lexical())->join($external->pieces()));
    }
}
