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
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\SecurityContext;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\SqlSecurity;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(SqlSecurity::class)]
#[Medium]
final class SqlSecurityTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string, SecurityContext}>
     */
    public static function providerRenderWritesTheContextKeyword(): iterable
    {
        yield 'MySQL 5.6 DEFINER' => ['mysql-5.6.51', 'alter procedure p sql security definer', 'ALTER PROCEDURE p SQL SECURITY DEFINER', SecurityContext::Definer];
        yield 'MySQL 8.0 INVOKER' => ['mysql-8.0.44', 'alter function shop.f sql security invoker', 'ALTER FUNCTION shop.f SQL SECURITY INVOKER', SecurityContext::Invoker];
        yield 'MySQL 9.1 INVOKER' => ['mysql-9.1.0', 'alter procedure p sql security invoker', 'ALTER PROCEDURE p SQL SECURITY INVOKER', SecurityContext::Invoker];
    }

    #[DataProvider('providerRenderWritesTheContextKeyword')]
    public function testRenderWritesTheContextKeyword(string $release, string $sql, string $expected, SecurityContext $context): void
    {
        $alter = (new Semantics(Dialect::MySql, $release))->analyze($sql);
        $statement = $alter->statement;
        self::assertInstanceOf(AlterRoutine::class, $statement);
        $characteristic = $statement->characteristics[0] ?? null;
        self::assertInstanceOf(SqlSecurity::class, $characteristic);

        self::assertSame($context, $characteristic->context);
        self::assertSame($expected, $alter->toString());
    }

    public function testRenderWritesAConstructedCharacteristic(): void
    {
        $out = new Output(new Codec((new Semantics(Dialect::MySql))->profile()->grammar));
        (new SqlSecurity(SecurityContext::Definer))->render($out);

        self::assertSame('SQL SECURITY DEFINER', (new Lexical())->join($out->pieces()));
    }
}
