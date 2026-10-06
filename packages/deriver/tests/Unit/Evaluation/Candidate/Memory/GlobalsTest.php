<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate\Memory;

use Deriver\Evaluation\Candidate\Memory\Globals as Subject;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture as F;

#[CoversNothing]
final class GlobalsTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testEffectUsesTheKnownCallPosition(): void
    {
        $engine = F::evaluator('function change(){global $g;$g=2;}function target(){global $g;$g=1;change();return $g;}');
        self::assertSame(2, F::value($engine)->native());
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testOriginFindsScriptInitializers(): void
    {
        $engine = F::evaluator('$cfg=["x"=>3];function target(){global $cfg;return $cfg["x"];}');
        self::assertSame(3, F::value($engine)->native());
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testDeclaresDistinguishesLocalFromGlobal(): void
    {
        $engine = F::evaluator('function target(){global $g;return $g;}');
        $frame = F::frame($engine);
        self::assertTrue((new Subject())->declares($frame, 'g'));
        self::assertFalse((new Subject())->declares($frame, 'local'));
    }

}
