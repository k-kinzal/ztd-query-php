<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\PartitionExpressions;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Partition\Problem\InvalidPartitionExpression;
use SqlSemantics\Statement\Scalar;

#[CoversClass(PartitionExpressions::class)]
#[Small]
final class PartitionExpressionsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerExpressions(): iterable
    {
        yield 'session function' => ['USER()', true];
        yield 'nested nondeterminism' => ['ABS(RAND())', true];
        yield 'system variable' => ['@@sql_mode', true];
        yield 'user variable' => ['@x', true];
        yield 'clock' => ['NOW()', true];
        yield 'stored function' => ['custom_function(1)', true];
        yield 'server version checked later' => ['VERSION()', false];
        yield 'constant checked later' => ['ABS(1)', false];
        yield 'deterministic disallowed function checked later' => ['SIN(1)', false];
        yield 'timestamp conversion checked later' => ['UNIX_TIMESTAMP(1)', false];
        yield 'current timestamp' => ['UNIX_TIMESTAMP()', true];
    }

    #[DataProvider('providerExpressions')]
    public function testForbiddenDistinguishesEarlyAndLaterChecks(string $sql, bool $forbidden): void
    {
        $expression = (new Semantics(Dialect::MySql))->analyze('SELECT ' . $sql)->field(0)->expression;
        self::assertInstanceOf(Scalar::class, $expression);
        self::assertSame($forbidden, (new PartitionExpressions())->forbidden($expression, GrammarRelease::MySql847));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerEarlierErrors(): iterable
    {
        yield 'unknown system variable' => ['@@unknown_system_variable'];
        yield 'incorrect function arity' => ['RAND(1,2)'];
    }

    #[DataProvider('providerEarlierErrors')]
    public function testDerivePreservesEarlierFunctionAndVariableErrors(string $expression): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('ALTER TABLE missing PARTITION BY HASH (' . $expression . ')');
        self::assertNotEmpty($operation->facts->diagnostics);
        self::assertSame([], array_values(array_filter($operation->facts->warnings, static fn ($warning): bool => $warning instanceof ParseFailure)));
    }

    public function testDerivePreservesWarningsAndTheOriginalOccurrenceLocation(): void
    {
        $sql = 'ALTER TABLE missing PARTITION BY HASH ((FOUND_ROWS()))';
        $operation = (new Semantics(Dialect::MySql))->analyze($sql);
        self::assertInstanceOf(Deprecation::class, $operation->facts->warnings[0]);
        $failure = $operation->facts->warnings[1];
        self::assertInstanceOf(ParseFailure::class, $failure);
        self::assertTrue($failure->aborts);
        self::assertInstanceOf(InvalidPartitionExpression::class, $failure->problem);
        $origin = $operation->sources->of($failure->problem->expression);
        self::assertNotNull($origin);
        self::assertSame('(FOUND_ROWS())', substr($sql, $origin->offset, $origin->length));
    }
}
