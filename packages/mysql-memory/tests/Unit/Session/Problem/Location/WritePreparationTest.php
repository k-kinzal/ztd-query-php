<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Problem\Location;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Problem\Location\WritePreparation;
use MySqlMemory\Session\Problem\Locations;
use MySqlMemory\Session\Problems;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(WritePreparation::class)]
#[Small]
final class WritePreparationTest extends TestCase
{
    #[TestWith(['5.6.51', true, 1])]
    #[TestWith(['5.6.51', false, 0])]
    #[TestWith(['5.7.44', true, 0])]
    #[TestWith(['8.4.7', true, 0])]
    public function testDeferredRetainsOnlyTheLegacyPreparedAssignmentCondition(string $release, bool $prepared, int $count): void
    {
        $session = (new Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t(a INT)');
        $session->query('CREATE TABLE u(a INT)');
        $operation = $session->analyze('UPDATE t JOIN u ON missing_join SET t.a=missing_value WHERE missing_filter');
        [$located] = (new Locations())->located($operation, (new Problems())->calls($operation), $operation->statementInformation()->diagnostics, $session, $prepared);

        $deferred = (new WritePreparation())->deferred($operation, $session, $prepared, $located);

        self::assertCount(3, $located);
        self::assertCount($count, $deferred);
        self::assertCount(3 - $count, array_diff_key($located, $deferred));
    }

    /**
     * @param list<int> $order A semantic role's resolution position
     * @param list<array{int, string}> $following Expected cleanup conditions
     */
    #[TestWith(['5.6.51', false, [6, 0], [[1105, 'Unknown error']]])]
    #[TestWith(['5.7.44', false, [2, 0], [[1105, 'Unknown error']]])]
    #[TestWith(['5.7.44', false, [1, 0], []])]
    #[TestWith(['5.7.44', true, [6, 0], []])]
    #[TestWith(['8.4.7', false, [6, 0], []])]
    public function testReportedPreservesOnlyTheReachedLegacyCleanup(string $release, bool $prepared, array $order, array $following): void
    {
        $session = (new Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t(a INT)');
        $session->query('CREATE TABLE u(a INT)');
        $operation = $session->analyze('UPDATE t,u SET t.a=1');
        $error = QueryError::InvalidGroupFunctionUse->error();

        $reported = (new WritePreparation())->reported($error, $operation, $session, $prepared, $order);

        self::assertSame($error->getCode(), $reported->getCode());
        self::assertSame($error->getMessage(), $reported->getMessage());
        self::assertSame($following, $reported->following);
    }
}
