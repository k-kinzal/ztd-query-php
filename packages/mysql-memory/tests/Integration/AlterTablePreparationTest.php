<?php

declare(strict_types=1);

namespace Tests\Integration;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Access\Preparation;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversNothing]
#[Medium]
final class AlterTablePreparationTest extends TestCase
{
    #[DataProvider('providerUnqualifiedTargets')]
    public function testPreparationRequiresADatabaseWithoutReadingSql(string $version, string $sql): void
    {
        $session = (new Instance($version, databases: ['pbt']))->connect();
        $statement = $session->analyze($sql);
        $session->text = '';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);
        (new Preparation())->check($statement, $session);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerUnqualifiedTargets(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() !== 'mysql') {
                continue;
            }
            $targets = in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? ['t', '.t'] : ['t'];
            foreach ($targets as $target) {
                foreach (['ADD COLUMN j INT', 'ADD COLUMN j INT(0)', 'ENGINE = unknown_engine'] as $command) {
                    yield $release->value . ' ' . $target . ' ' . $command => [substr($release->value, 6), 'ALTER TABLE ' . $target . ' ' . $command];
                }
            }
        }
    }
}
