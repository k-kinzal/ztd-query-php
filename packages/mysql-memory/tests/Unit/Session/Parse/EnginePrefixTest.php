<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\EnginePrefix;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(EnginePrefix::class)]
#[Small]
final class EnginePrefixTest extends TestCase
{
    public function testBeforeFindsTheEngineWithoutCreatingTheTable(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $sql = 'CREATE TABLE t(a INT) ENGINE=bad START TRANSACTION';
        $tokens = $session->semantics()->parser()->tokenize($sql);

        $errors = (new EnginePrefix())->before($tokens, $sql, (int) strpos($sql, 'START'), $session);

        self::assertSame([(int) strpos($sql, 'ENGINE')], array_keys($errors));
        self::assertSame(1286, array_values($errors)[0]->getCode());
        self::assertSame("Unknown storage engine 'bad'", array_values($errors)[0]->getMessage());
        self::assertNull($session->instance->dictionary->table('', 't'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerPrefixes(): iterable
    {
        yield 'known engine' => ['CREATE TABLE t(a INT) ENGINE=InnoDB START TRANSACTION'];
        yield 'incomplete table definition' => ['CREATE TABLE t(a INT, START TRANSACTION'];
        yield 'other statement' => ['SELECT 1 START TRANSACTION'];
        yield 'other creation' => ['CREATE DATABASE d START TRANSACTION'];
    }

    #[DataProvider('providerPrefixes')]
    public function testBeforeLeavesOtherPrefixesToTheOriginalDiagnostic(string $sql): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $tokens = $session->semantics()->parser()->tokenize($sql);

        self::assertSame([], (new EnginePrefix())->before($tokens, $sql, (int) strpos($sql, 'START'), $session));
    }
    public function testConditionsSelectsTheEngineErrorBeforeLaterSyntax(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query("SET sql_mode='NO_ENGINE_SUBSTITUTION'");
        $sql = 'CREATE TABLE t(a INT) ENGINE=bad START TRANSACTION';
        $fallback = \MySqlMemory\Error\Family\StatementError::ParseError->error('START TRANSACTION', 1);
        $tokens = $session->semantics()->parser()->tokenize($sql);

        [$end, $warnings, $error] = (new EnginePrefix())->conditions($tokens, $sql, (int) strpos($sql, 'START'), $session, $fallback);

        self::assertSame((int) strpos($sql, 'ENGINE'), $end);
        self::assertSame([], $warnings);
        self::assertSame(1286, $error->getCode());
    }

    public function testConditionsKeepsEngineWarningsBeforeTheSyntaxError(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query("SET sql_mode=''");
        $sql = 'CREATE TABLE t(a INT) ENGINE=bad START TRANSACTION';
        $fallback = \MySqlMemory\Error\Family\StatementError::ParseError->error('START TRANSACTION', 1);
        $tokens = $session->semantics()->parser()->tokenize($sql);

        [$end, $warnings, $error] = (new EnginePrefix())->conditions($tokens, $sql, (int) strpos($sql, 'START'), $session, $fallback);

        self::assertSame((int) strpos($sql, 'START'), $end);
        self::assertSame([1286], array_values(array_map(static fn ($warning): int => $warning->getCode(), $warnings)));
        self::assertSame($fallback, $error);
    }

}
