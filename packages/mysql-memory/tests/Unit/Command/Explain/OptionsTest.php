<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Explain;

use MySqlMemory\Command\Explain\Options;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Options::class)]
#[Small]
final class OptionsTest extends TestCase
{
    #[DataProvider('providerRefusals')]
    public function testCheckUsesTheRetainedOptions(string $version, string $sql, int $code): void
    {
        $session = (new Instance($version))->connect();
        $statement = $session->semantics()->analyze($sql);
        $session->text = 'Unrelated input';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode($code);
        (new Options())->check($statement, $session);
    }

    public function testCheckAcceptsAnOrdinaryConnectionRequest(): void
    {
        $session = (new Instance())->connect();
        (new Options())->check($session->semantics()->analyze('EXPLAIN FOR CONNECTION 0'), $session);
        (new Options())->check(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), $session);
        self::assertSame([], $session->diagnostics->conditions);
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function providerRefusals(): iterable
    {
        yield 'unknown' => ['8.4.7', 'EXPLAIN FORMAT=XML FOR CONNECTION 0', 1791];
        yield 'legacy tree' => ['5.7.44', 'EXPLAIN FORMAT=TREE FOR CONNECTION 0', 1791];
        yield 'analyze' => ['8.0.44', 'EXPLAIN ANALYZE FORMAT=TRADITIONAL FOR CONNECTION 0', 1235];
        yield 'implicit format' => ['8.4.7', 'EXPLAIN INTO @v FOR CONNECTION 0', 6006];
        yield 'wrong format' => ['8.4.7', 'EXPLAIN FORMAT=TREE INTO @v FOR CONNECTION 0', 6007];
        yield 'connection into' => ['8.4.7', 'EXPLAIN FORMAT=JSON INTO @v FOR CONNECTION 0', 6009];
        yield 'invalid target' => ['8.4.7', "EXPLAIN FORMAT=JSON INTO @'' FOR CONNECTION 0", 3061];
        yield 'unknown before target' => ['8.4.7', "EXPLAIN FORMAT=XML INTO @'' FOR CONNECTION 0", 1791];
        yield 'legacy analyze into' => ['8.1.0', 'EXPLAIN ANALYZE FORMAT=JSON INTO @v SELECT 1', 6005];
    }
}
