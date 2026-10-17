<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Problem\Location;

use MySqlMemory\Instance;
use MySqlMemory\Session\Locator;
use MySqlMemory\Session\Problem\Location\Calls;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(Calls::class)]
#[Medium]
final class CallsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerArityOrder(): iterable
    {
        yield 'legacy 5.6' => ['5.6.51', 0];
        yield 'legacy 5.7' => ['5.7.44', 0];
        yield 'modern 8.0' => ['8.0.44', 1];
        yield 'modern 8.4' => ['8.4.7', 1];
        yield 'modern 9.1' => ['9.1.0', 1];
    }

    #[DataProvider('providerArityOrder')]
    public function testLocatedUsesTheCurrentTargetAndTheReleasePreparationOrder(string $release, int $count): void
    {
        $session = (new Instance($release))->connect();
        $session->query('CREATE DATABASE d; USE d');
        $operation = $session->semantics()->analyze('SELECT D.f(0, absent_column)');
        $locator = (new Locator(release: $session->settings()->release()))->statement($operation->statement);
        $observed = array_map(static function (string $setup) use ($session, $locator): array {
            $session->query($setup);
            return (new Calls())->located($locator, $session, []);
        }, ['DO 0', 'CREATE FUNCTION f(x INT) RETURNS INT DETERMINISTIC RETURN x', 'DROP FUNCTION f']);

        self::assertSame([0, $count, 0], array_map(count(...), $observed));
        self::assertSame([[], $count === 0 ? [] : [1318], []], array_map(static fn (array $located): array => array_map(static fn (array $entry): ?int => $entry[0] instanceof \MySqlMemory\Error\SqlError ? $entry[0]->getCode() : null, array_values($located)), $observed));
    }
}
