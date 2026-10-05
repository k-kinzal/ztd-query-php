<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate\Language;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture as F;

#[CoversNothing]
final class ExpressionsTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testConstantUsesLexicalDeclarations(): void
    {
        $engine = F::evaluator('class C{const X=2+3;}function target(){return C::X;}');
        self::assertSame(5, F::value($engine)->native());
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testCopyAllocatesADistinctIdentity(): void
    {
        $engine = F::evaluator('class C{}function target(){$a=new C;$b=clone $a;return $a===$b;}');
        self::assertSame(false, F::value($engine)->native());
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testStringUsesCapturedMagicMethod(): void
    {
        $engine = F::evaluator('class C{function __toString(){return "text";}}function target(){return (string)new C;}');
        self::assertSame('text', F::value($engine)->native());
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testEnumCasesKeepsDeclarationOrder(): void
    {
        $engine = F::evaluator('enum E:string{case A="a";case B="b";}function target(){return E::cases()[1]->name;}');
        self::assertSame('B', F::value($engine)->native());
    }

}
