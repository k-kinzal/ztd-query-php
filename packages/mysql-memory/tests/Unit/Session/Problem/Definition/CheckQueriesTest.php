<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Problem\Definition;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Problem\Definition\CheckQueries;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(CheckQueries::class)]
#[Small]
final class CheckQueriesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerPreparation(): iterable
    {
        yield 'missing subquery table' => ['CREATE TABLE t(a INT,CHECK(1 IN(TABLE absent)))', 1146];
        yield 'existing subquery table' => ['CREATE TABLE t(a INT,CHECK(1 IN(TABLE present)))', 3815];
        yield 'existing target' => ['CREATE TABLE present(a INT,CHECK(1 IN(TABLE present)))', 3815];
        yield 'unresolved subquery column' => ['CREATE TABLE t(a INT,CHECK(1 IN(SELECT missing FROM present)))', 3815];
        yield 'explicit row widths' => ['CREATE TABLE t(a INT,CHECK(1 IN(TABLE absent) AND (1,2)=(3,4,5)))', 1241];
        yield 'foreign table checked later' => ['CREATE TABLE t(a INT,FOREIGN KEY(a) REFERENCES absent(a),CHECK(EXISTS(TABLE absent2)))', 1146];
    }

    #[DataProvider('providerPreparation')]
    public function testPrepareRespectsDefinitionAndQueryStages(string $sql, int $code): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE present(a INT,b INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode($code);
        $session->query($sql);
    }
}
