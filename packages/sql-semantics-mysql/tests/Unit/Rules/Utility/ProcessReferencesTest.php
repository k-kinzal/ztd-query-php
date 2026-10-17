<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Utility\ProcessReferences;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\Kill;

#[CoversClass(ProcessReferences::class)]
#[Medium]
final class ProcessReferencesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerDependencies(): iterable
    {
        yield 'constant scalar subquery' => ['KILL (SELECT 0)', false];
        yield 'dual' => ['KILL (SELECT 0 FROM DUAL)', false];
        yield 'table' => ['KILL (SELECT 0 FROM missing)', true];
        yield 'explicit table' => ['KILL (TABLE missing)', true];
        yield 'unqualified stored function' => ['KILL missing()', true];
        yield 'qualified stored function' => ['KILL d.abs(0)', true];
        yield 'native function' => ['KILL ABS(0)', false];
    }

    #[DataProvider('providerDependencies')]
    public function testCheckDistinguishesConstantSubqueriesFromExternalDependencies(string $sql, bool $forbidden): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze($sql);
        $dependencies = array_filter($operation->facts->diagnostics, static fn ($diagnostic): bool => $diagnostic instanceof NotSupportedYet && $diagnostic->feature === Kill::DEPENDENCIES);

        self::assertCount($forbidden ? 1 : 0, $dependencies);
    }
}
