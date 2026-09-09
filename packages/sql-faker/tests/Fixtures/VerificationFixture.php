<?php

declare(strict_types=1);

namespace Tests\Fixtures\SqlFaker;

use Closure;
use RuntimeException;
use SqlFaker\Coverage\GrammarCoverage;

/**
 * Records a completed SQL output without introducing a database dependency into persistence tests.
 */
final class VerificationFixture
{
    /**
     * The first original production is both reached and emitted, with the real SQL hash.
     */
    public static function coverage(string $sql = 'SELECT 1'): GrammarCoverage
    {
        $coverage = CoverageFixture::coverage();
        $coverage->beginGeneration('stmt', []);
        $coverage->beginAttempt(0);
        $coverage->record(0, null, null, 'stmt', $coverage->inventory()->denominator[0], 'selected');
        $coverage->commitAttempt(hash('sha256', $sql));
        $coverage->endGeneration();
        return $coverage;
    }

    /**
     * Builds one complete two-lexeme candidate with version, spacing, rejection, and rewrite observations.
     */
    public static function lexicalCoverage(): GrammarCoverage
    {
        $coverage = CoverageFixture::coverage();
        $coverage->beginGeneration('stmt', []);
        $coverage->beginAttempt(0);
        $coverage->record(0, null, null, 'stmt', $coverage->inventory()->denominator[0], 'selected');
        $sequence = \SqlFaker\Grammar\Generation\Token\TerminalSequence::fromNames(['OLD']);
        $changed = $sequence->replace(0, 1, [$sequence->terminals[0]->replaced('PAIR', 'fixture-rewrite')], 'fixture-rewrite');
        $coverage->recordSequence($changed);
        $left = new \SqlFaker\Grammar\Generation\Lexeme\Lexeme('A', 'identifier', $changed->terminals[0], 'name-left');
        $right = new \SqlFaker\Grammar\Generation\Lexeme\Lexeme('B', 'identifier', $changed->terminals[0], 'name-right');
        $candidate = new \SqlFaker\Grammar\Generation\Lexeme\LexemeSequence([$left, $right], 'pair:A+B', provenance: static fn (): iterable => ['pair', 'version-case:fixture-v1']);
        $coverage->recordOutput(new \SqlFaker\Grammar\Generation\Output\ResolvedOutput([
            new \SqlFaker\Grammar\Generation\Output\OutputPart($left, ' ', $candidate->id, ['identifier-boundary']),
            new \SqlFaker\Grammar\Generation\Output\OutputPart($right, '', $candidate->id),
        ], candidates: [$candidate], rejections: [['index' => 0, 'candidate' => 'bad-pair', 'rules' => ['identifier-boundary']]]));
        $coverage->commitAttempt(hash('sha256', 'A B'));
        $coverage->endGeneration();
        return $coverage;
    }

    /**
     * Captures a failing oracle action so tests can inspect both the propagated failure and persisted observation.
     * @param Closure(): mixed $action
     * @throws RuntimeException When the expected oracle failure was not raised
     */
    public static function failure(Closure $action): \SqlFaker\Fuzz\Target\SyntaxFailure|\SqlFaker\Fuzz\Target\InfrastructureFailure
    {
        try {
            $action();
        } catch (\SqlFaker\Fuzz\Target\SyntaxFailure|\SqlFaker\Fuzz\Target\InfrastructureFailure $failure) {
            return $failure;
        }
        throw new RuntimeException('Expected oracle failure was not raised.');
    }
}
