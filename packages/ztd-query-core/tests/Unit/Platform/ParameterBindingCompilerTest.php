<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeParameterBindingCompiler;

#[CoversNothing]
final class ParameterBindingCompilerTest extends TestCase
{
    public function testCompileLeavesAStatementWithNoValuesToBindAlone(): void
    {
        $compiled = (new FakeParameterBindingCompiler())->compile('SELECT 1', null);

        self::assertSame('SELECT 1', $compiled['sql']);
        self::assertNull($compiled['params']);
    }

    public function testCompileWritesTheStatementInTheFormTheDriverTakes(): void
    {
        $compiled = (new FakeParameterBindingCompiler())
            ->compile('SELECT * FROM t WHERE a = :a AND b = :b', ['a' => 1, 'b' => 2]);

        self::assertSame('SELECT * FROM t WHERE a = ? AND b = ?', $compiled['sql']);
    }

    public function testCompilePutsTheValuesInTheOrderTheStatementNeedsThem(): void
    {
        $compiled = (new FakeParameterBindingCompiler())
            ->compile('SELECT * FROM t WHERE b = :b AND a = :a', ['a' => 1, 'b' => 2]);

        self::assertSame([2, 1], $compiled['params']);
    }
}
