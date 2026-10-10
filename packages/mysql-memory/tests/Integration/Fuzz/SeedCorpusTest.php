<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Comparison;
use Fuzz\Target\SeedCorpus;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Medium]
final class SeedCorpusTest extends TestCase
{
    public function testInputsPreservesTheCanonicalSeedContract(): void
    {
        $corpus = new SeedCorpus('mysql-8.4.7');
        $inputs = $corpus->inputs(dirname(__DIR__, 3) . '/vendor/k-kinzal/sql-faker/seeds/mysql/mysql-8.4.7');
        $seed = $inputs->current();

        self::assertSame('IDENT_sys-1.txt', $seed->file);
        self::assertSame('05000000000003000000000001000000000000', bin2hex($seed->input));
        self::assertSame("ALTER LOGFILE GROUP `name` ADD UNDOFILE 'text'", $seed->sql);
        self::assertCount(3114, $corpus->denominator());
        self::assertNotEmpty($seed->reached);
        self::assertNotEmpty($seed->emitted);
    }

    public function testRecordDoesNotCountVolatileOrDifferentStatementsAsVerifiedCoverage(): void
    {
        $corpus = new SeedCorpus('mysql-8.4.7');
        $inputs = $corpus->inputs(dirname(__DIR__, 3) . '/vendor/k-kinzal/sql-faker/seeds/mysql/mysql-8.4.7');
        $seed = $inputs->current();
        self::assertSame('volatile', $corpus->record($seed, new Comparison(true)));
        self::assertSame('different', $corpus->record($seed, new Comparison(false, 'mismatch')));
        self::assertSame(0, $corpus->summary()['matched']);
        self::assertSame('matched', $corpus->record($seed, new Comparison(false)));
        $summary = $corpus->summary();

        self::assertGreaterThan(0, $summary['matched']);
        self::assertSame(['matched' => 1, 'different' => 1, 'volatile' => 1], $summary['counts']);
        self::assertNotEmpty($summary['unreached']);
        self::assertNotEmpty($summary['unverified']);
    }
}
