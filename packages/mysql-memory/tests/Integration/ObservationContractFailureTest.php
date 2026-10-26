<?php

declare(strict_types=1);

namespace Tests\Integration;

use Closure;
use Fuzz\Observation\Password\Retention;
use Fuzz\Observation\ProbeFailure;
use Fuzz\Observation\Process;
use Fuzz\Observation\RandomPasswords;
use Fuzz\Observation\Record;
use Fuzz\Observation\Status;
use Fuzz\Observation\TableTimes;
use MySqlMemory\Server\MemoryDatabase;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class ObservationContractFailureTest extends TestCase
{
    /**
     * @param Closure(PDO, Record): mixed $sample
     */
    #[DataProvider('providerSamples')]
    public function testUnreadableContractInputsFailAndRetainEvidence(Closure $sample, int $mode, bool $emulate): void
    {
        $server = MemoryDatabase::start('8.4.7');
        try {
            $connection = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => $mode, PDO::ATTR_EMULATE_PREPARES => $emulate]);
            $record = new Record('memory', 'DO 0', '');
            $record->raw = ['results' => [['affected' => 0, 'lastInsertId' => '0']], 'warnings' => []];
            $record->phase = 'comparison contracts';
            $server->stop();
            $failure = null;
            try {
                $sample($connection, $record);
            } catch (ProbeFailure $error) {
                $failure = $error;
            }

            self::assertNotNull($failure);
            self::assertCount(1, $record->probes);
            self::assertSame($failure->record, $record->probes[0]);
            self::assertArrayHasKey('error', $failure->record->raw);
            self::assertSame(['results' => [['affected' => 0, 'lastInsertId' => '0']], 'warnings' => []], $record->raw);
            self::assertSame('comparison contracts', $record->phase);
            self::assertSame($emulate, $connection->getAttribute(PDO::ATTR_EMULATE_PREPARES));
            self::assertSame($mode, $connection->getAttribute(PDO::ATTR_ERRMODE));
        } finally {
            $server->stop();
        }
    }

    /**
     * @return iterable<string, array{Closure(PDO, Record): mixed, int, bool}>
     */
    public static function providerSamples(): iterable
    {
        $samples = [
            'process sample' => static fn (PDO $pdo, Record $record) => Process\Sample::read($pdo, $record),
            'process setup' => static fn (PDO $pdo, Record $record) => Process\Listing::prepare($pdo, $record),
            'process listing' => static fn (PDO $pdo, Record $record) => Process\Listing::capture($pdo, $pdo, $record),
            'status snapshot' => static fn (PDO $pdo, Record $record) => Status\Snapshot::capture($pdo, $record),
            'status listing' => static fn (PDO $pdo, Record $record) => Status\Listing::capture($pdo, 'SHOW STATUS', $record),
            'password' => static fn (PDO $pdo, Record $record) => RandomPasswords::capture($pdo, false, $record),
            'retention' => static fn (PDO $pdo, Record $record) => Retention::capture($pdo, $record),
            'retained credential' => static fn (PDO $pdo, Record $record) => (new Retention('previous'))->retained($pdo, $record),
            'discarded credential' => static fn (PDO $pdo, Record $record) => (new Retention('previous'))->discarded($pdo, $record),
            'table clock' => static fn (PDO $pdo, Record $record) => (new TableTimes())->now($pdo, $record),
        ];
        foreach ($samples as $name => $sample) {
            foreach ([PDO::ERRMODE_EXCEPTION, PDO::ERRMODE_SILENT] as $mode) {
                foreach ([true, false] as $emulate) {
                    yield $name . ', ' . $mode . ($emulate ? ', text' : ', prepared') => [$sample, $mode, $emulate];
                }
            }
        }
    }
}
