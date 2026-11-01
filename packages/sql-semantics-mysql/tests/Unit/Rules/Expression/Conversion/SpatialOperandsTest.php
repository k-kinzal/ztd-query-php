<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression\Conversion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Rules\Expression\Conversion\SpatialOperands;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\SpatialKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Spatial;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(SpatialOperands::class)]
#[Small]
final class SpatialOperandsTest extends TestCase
{
    /**
     * @return iterable<string, array{GrammarRelease, ArithmeticOperator|UnaryOperator, bool}>
     */
    public static function providerOperators(): iterable
    {
        yield '5.6 addition' => [GrammarRelease::MySql5651, ArithmeticOperator::Plus, false];
        yield 'legacy addition' => [GrammarRelease::MySql5744, ArithmeticOperator::Plus, true];
        yield 'modern addition' => [GrammarRelease::MySql847, ArithmeticOperator::Plus, true];
        yield 'legacy bits' => [GrammarRelease::MySql5744, ArithmeticOperator::BitAnd, false];
        yield 'modern bits' => [GrammarRelease::MySql847, ArithmeticOperator::BitAnd, true];
        yield 'legacy inversion' => [GrammarRelease::MySql5744, UnaryOperator::Invert, false];
        yield 'modern inversion' => [GrammarRelease::MySql847, UnaryOperator::Invert, true];
        yield 'negation' => [GrammarRelease::MySql847, UnaryOperator::Minus, true];
        yield 'identity' => [GrammarRelease::MySql847, UnaryOperator::Plus, false];
        yield 'truth' => [GrammarRelease::MySql847, UnaryOperator::Not, false];
    }

    #[DataProvider('providerOperators')]
    public function testCheckSeparatesSpatialArithmeticFromLegacyBitsAndUnaryIdentity(GrammarRelease $release, ArithmeticOperator|UnaryOperator $operator, bool $refused): void
    {
        $derivation = new Derivation(new AnalysisContext(new LanguageProfile($release), [new Name('d')]));
        (new SpatialOperands())->check($derivation, $operator, [new Known(new Spatial(SpatialKind::Point))]);
        self::assertCount($refused ? 1 : 0, $derivation->facts()->diagnostics);
    }

    public function testCheckAcceptsExplicitlyConvertedBinaryPayloads(): void
    {
        $derivation = new Derivation(new AnalysisContext(new LanguageProfile(GrammarRelease::MySql847), [new Name('d')]));
        (new SpatialOperands())->check($derivation, ArithmeticOperator::Plus, [new Known(Domain::string(25, Collation::binary()))]);
        self::assertSame([], $derivation->facts()->diagnostics);
    }
}
