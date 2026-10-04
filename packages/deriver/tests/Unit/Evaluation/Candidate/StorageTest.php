<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture as F;

#[CoversNothing]
final class StorageTest extends TestCase
{
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testReadSelectsTheReachingDefinition(): void
    {
        $e = F::evaluator('function target(){$x=1;$x=2;return $x;}');
        self::assertSame(2, F::value($e)->native());
    }
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testModifiedFindsEscapingLocals(): void
    {
        $e = F::evaluator('function target($x){unknown($x);return $x;}');
        self::assertTrue((new \Deriver\Evaluation\Candidate\Storage($e))->modified(F::frame($e), 'x'));
    }
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testSearchFollowsBranchSpecificWrites(): void
    {
        $e = F::evaluator('function target(){if(true){$x=4;}else{$x=6;}return $x;}');
        self::assertSame(4, F::value($e)->native());
    }
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testWritePreservesCompoundArithmetic(): void
    {
        $e = F::evaluator('function target(){$x=3;$x+=4;return $x;}');
        self::assertSame(7, F::value($e)->native());
    }
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testKeySeparatesDifferentLocals(): void
    {
        $e = F::evaluator('function target($x,$y){return $x+$y;}');
        $f = F::frame($e);
        $a = F::instruction($f, 'local');
        self::assertSame('local:x', (new \Deriver\Evaluation\Candidate\Storage($e))->key($f, $a->result));
    }
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testTypeDoesNotRestoreAnOverwrittenParameter(): void
    {
        $e = F::evaluator('function target(PDO $x){$x=3;return $x;}');
        $f = F::frame($e);
        $r = F::instruction($f, 'read');
        $a = $f->graph->definitions[$r->operands[0]];
        self::assertSame('mixed', (new \Deriver\Evaluation\Candidate\Storage($e))->type($f, $a));
    }
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testInitialRetainsTheInputIdentity(): void
    {
        $e = F::evaluator('function target($x){return $x;}');
        $f = F::frame($e);
        $a = F::instruction($f, 'local');
        self::assertSame('$x', (new \Deriver\Evaluation\Candidate\Storage($e))->initial($f, $a->result, 64)->literal);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testElementWriteUpdatesTheDemandedParentArray(): void
    {
        $e = F::evaluator('function target(){$a=[1];$a[0]=7;return $a;}');
        self::assertSame([7], F::value($e)->native());
    }
}
