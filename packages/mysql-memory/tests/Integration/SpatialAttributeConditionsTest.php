<?php

declare(strict_types=1);

namespace Tests\Integration;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialRule;
use SqlSemantics\Platform\MySql\Statement\Server\Spatial\CreateSpatialReference;

#[CoversNothing]
#[Medium]
final class SpatialAttributeConditionsTest extends TestCase
{
    #[DataProvider('providerRepeatedAttributes')]
    public function testRepeatedAttributesRemainCompleteRequests(string $version, string $sql): void
    {
        $session = (new Instance($version))->connect();
        $statement = $session->analyze($sql);
        self::assertInstanceOf(CreateSpatialReference::class, $statement);
        self::assertSame($sql, $statement->toString());
        self::assertSame(SpatialRule::RepeatedAttribute, $statement->problem?->rule);
        $session->text = '';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3709);
        (new Execution($session))->statement($statement);
    }

    public function testConversionWarningsStopAtTheFirstRepeatedAttribute(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $reply = $session->run("CREATE SPATIAL REFERENCE SYSTEM 7 NAME '😀' NAME 'b' DESCRIPTION '😀'");
        self::assertInstanceOf(SqlError::class, $reply[0]);
        self::assertSame(3709, $reply[0]->getCode());
        self::assertSame([1300, 3709], array_column($session->diagnostics->conditions, 1));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerRepeatedAttributes(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() !== 'mysql' || in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
                continue;
            }
            foreach ([
                "CREATE OR REPLACE SPATIAL REFERENCE SYSTEM 2147483648 DEFINITION 'text' NAME 'text' NAME 'text'",
                "CREATE SPATIAL REFERENCE SYSTEM 0 NAME 'a' NAME 'b'",
                "CREATE SPATIAL REFERENCE SYSTEM 4294967296 DEFINITION 'd' DEFINITION 'e'",
                "CREATE SPATIAL REFERENCE SYSTEM 7 ORGANIZATION 'a' IDENTIFIED BY 1 ORGANIZATION 'b' IDENTIFIED BY 2",
                "CREATE SPATIAL REFERENCE SYSTEM IF NOT EXISTS 4326 DESCRIPTION 'a' DESCRIPTION 'b'",
                "CREATE SPATIAL REFERENCE SYSTEM 7 NAME '😀' NAME 'b' DESCRIPTION '😀'",
            ] as $index => $sql) {
                yield $release->value . ' ' . $index => [substr($release->value, 6), $sql];
            }
        }
    }
}
