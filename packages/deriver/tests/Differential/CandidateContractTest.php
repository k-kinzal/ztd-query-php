<?php

declare(strict_types=1);

namespace Tests\Differential;

use Deriver\Analyzer;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Query\ReturnQuery;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Programs\CandidateContractPrograms;
use Tests\Fake\RuntimeOracle;

/**
 * Compares closed candidate expansions with the independent PHP 8.3 runtime.
 */
#[CoversNothing]
final class CandidateContractTest extends TestCase
{
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    #[DataProviderExternal(CandidateContractPrograms::class, 'cases')]
    public function testClosedExpansionsMatchTheRuntime(string $source): void
    {
        $expected = RuntimeOracle::evaluate($source)->native();
        $result = (new Analyzer())->open(new ProjectInput([new SourceFile('candidate.php', $source)]))->derive(new ReturnQuery('target'));
        self::assertCount(1, $result);
        self::assertSame('analyzed', $result->candidates[0]->type);
        self::assertSame($expected, $result->candidates[0]->result);
    }
}
