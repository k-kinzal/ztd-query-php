<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Choice;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Choice\ChoiceSource;

#[CoversClass(ChoiceSource::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Grammar::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Production::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\ProductionRule::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Terminal::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\NonTerminal::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\GrammarCoverageInventory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\CoverageException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\GrammarCoverage::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\GeneratorRevision::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\CoverageSnapshotStore::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\SnapshotValidation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\GenerationTrace::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\CoverageSets::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Choice\ByteChoices::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Derivation\CompletionCosts::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Derivation\DerivationNode::class)]
final class ChoiceSourceTest extends TestCase
{
    public function testSignedIntegerExtremesAndExhaustionRemainReachable(): void
    {
        $source = new ChoiceSource(Factory::create());
        $source->begin('');
        self::assertSame(PHP_INT_MIN, $source->numberBetween(PHP_INT_MIN, PHP_INT_MAX));
        $source->begin("\x01" . str_repeat("\0", PHP_INT_SIZE) . "\x01");
        self::assertSame(PHP_INT_MAX, $source->numberBetween(PHP_INT_MIN, PHP_INT_MAX));
        $source->begin("\x00" . str_repeat("\0", PHP_INT_SIZE) . "\x01");
        self::assertSame(-1, $source->numberBetween(PHP_INT_MIN, PHP_INT_MAX));
        $source->begin("\x01" . str_repeat("\0", PHP_INT_SIZE) . "\x00");
        self::assertSame(0, $source->numberBetween(PHP_INT_MIN, PHP_INT_MAX));
    }

    public function testNumberBetweenConsumesChoicesAndUsesDeterministicBounds(): void
    {
        $source = new ChoiceSource(Factory::create());
        $source->begin("\x02");
        self::assertSame(12, $source->numberBetween(10, 20));
        self::assertSame(10, $source->numberBetween(10, 20));
        $source->begin("\x02");
        self::assertSame(12, $source->numberBetween(10, 20));
    }

    public function testEndRestoresTheNormalFakerSource(): void
    {
        $faker = Factory::create();
        $source = new ChoiceSource($faker);
        $source->begin('');
        $source->end();
        $faker->seed(17);
        $expected = $faker->numberBetween(1, 1000);
        $faker->seed(17);
        self::assertSame($expected, $source->numberBetween(1, 1000));
    }


    public function testBeginWithEmptyBytesNeverConsumesFakerRandomness(): void
    {
        $source = new ChoiceSource(Factory::create());
        $source->begin('');
        self::assertSame(5, $source->numberBetween(5, 10));
        $source->begin('');
        self::assertSame(5, $source->numberBetween(5, 10));
    }
}
