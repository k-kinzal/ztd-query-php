<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Constraint;

use MySqlMemory\Command\Definition\Constraint\ColumnReferences;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ColumnReferences::class)]
#[Small]
final class ColumnReferencesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.0.1', '9.1.0'] as $release) {
            yield $release => [$release, str_starts_with($release, '9.')];
        }
    }

    #[DataProvider('providerReleases')]
    public function testKeyPreservesTheReleaseDependentConstraint(string $release, bool $enforced): void
    {
        $session = (new Instance($release))->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p(a INT PRIMARY KEY); INSERT INTO p VALUES(1); CREATE TABLE c(a INT REFERENCES p(a))');
        $result = $session->run('INSERT INTO c VALUES(2)')[0];

        self::assertInstanceOf($enforced ? SqlError::class : Completion::class, $result);
        self::assertSame($enforced ? 1452 : null, $result instanceof SqlError ? $result->getCode() : null);
        self::assertCount($enforced ? 1 : 0, $session->instance->dictionary->table('d', 'c')?->definition->foreignKeys ?? []);
    }
}
