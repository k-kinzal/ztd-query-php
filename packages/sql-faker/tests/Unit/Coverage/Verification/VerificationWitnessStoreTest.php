<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Coverage\Verification;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Coverage\CoverageException;
use SqlFaker\Coverage\CoverageSnapshotStore;
use SqlFaker\Coverage\Verification\VerificationWitnessStore;
use Tests\Fixtures\SqlFaker\CoverageFixture;

#[CoversClass(VerificationWitnessStore::class)]
#[UsesClass(CoverageSnapshotStore::class)]
#[UsesClass(CoverageException::class)]
final class VerificationWitnessStoreTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testRecordPreservesOriginalBytesWhenTheFuzzerCorpusIsNotAvailable(): void
    {
        $directory = CoverageFixture::directory();
        $input = "\0\xff\0";
        $sql = "SELECT '猫'";
        $store = new VerificationWitnessStore($directory);
        $store->record($input, $sql);
        $store->record($input, $sql);
        $key = hash('sha256', hash('sha256', $input) . ':' . hash('sha256', $sql));
        $reader = new CoverageSnapshotStore($directory, $key);
        $json = $reader->read();
        self::assertNotNull($json);
        self::assertSame(['inputHex' => '00ff00', 'sql' => $sql], json_decode($json, true, flags: JSON_THROW_ON_ERROR));
        unset($reader, $store);
        CoverageFixture::remove($directory);
    }

    public function testRecordReportsUnencodableSqlInsteadOfDroppingItsWitness(): void
    {
        $store = new VerificationWitnessStore(sys_get_temp_dir() . '/sql-faker-invalid-witness-' . bin2hex(random_bytes(8)));
        $this->expectException(CoverageException::class);
        $store->record('input', "\xff");
    }
}
