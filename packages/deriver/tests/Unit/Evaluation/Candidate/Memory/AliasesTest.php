<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate\Memory;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture as F;

#[CoversNothing]
final class AliasesTest extends TestCase
{
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testKeyTracksTheCellAssignedThroughAnotherName(): void
    {
        $e = F::evaluator('function target(){$x=1;$y=&$x;$y=4;return $x;}');
        self::assertSame(4, F::value($e)->native());
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testMapKeepsAnAliasBoundToItsCell(): void
    {
        $e = F::evaluator('function target(){$x=1;$y=&$x;return $y;}');
        $f = F::frame($e);
        $s = new \Deriver\Evaluation\Candidate\Storage($e);
        $map = (new \Deriver\Evaluation\Candidate\Memory\Aliases())->map($s, $f, 0, count($f->graph->body->blocks[0]->instructions));
        self::assertSame('local:x', $map['local:y']);
    }
    public function testMergeMarksConflictingCellsInsteadOfUnitingThem(): void
    {
        self::assertSame(['x' => 'unresolved-alias:x'], (new \Deriver\Evaluation\Candidate\Memory\Aliases())->merge([['x' => 'y'],[]]));
    }
}
