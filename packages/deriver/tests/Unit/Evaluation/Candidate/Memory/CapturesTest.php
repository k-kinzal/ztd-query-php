<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate\Memory;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Semantic\CandidateContractTest as C;

#[CoversNothing]
final class CapturesTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testValueFindsALexicalCaptureWithoutInvokingTheCallback(): void
    {
        $s = C::session('function owner(){$x="users";register(function()use($x){observe($x);});}');
        self::assertSame(['users'], C::native(C::argument($s)));
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testAtRetainsTheDefinitionAtClosureCreation(): void
    {
        $s = C::session('function owner(){$x=1;register(function()use($x){observe($x);});$x=2;}');
        self::assertSame([1], C::native(C::argument($s)));
    }
}
