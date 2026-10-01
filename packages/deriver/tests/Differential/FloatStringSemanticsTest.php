<?php

declare(strict_types=1);

namespace Tests\Differential;

use Deriver\Project\Configuration;
use Deriver\Project\TargetProfile;
use Deriver\Query\ReturnQuery;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;
use Tests\Fake\Programs\FloatStringPrograms;
use Tests\Fake\RuntimeOracle;

/**
 * Verifies float-to-string conversions against PHP 8.3 running under the same precision directive.
 */
#[CoversNothing]
#[Medium]
final class FloatStringSemanticsTest extends TestCase
{
    /**
     * @param int $precision Precision directive set in the runtime and captured in the target profile
     * @param string $source Trusted fixture source
     * @param Term $expected Expected observation
     * @throws JsonException If fixture metadata cannot be encoded
     */
    #[DataProviderExternal(FloatStringPrograms::class, 'cases')]
    public function testFloatConversionFollowsTheCapturedPrecision(int $precision, string $source, Term $expected): void
    {
        $runtime = '<?php ini_set("precision", "' . $precision . '");' . substr($source, 5);
        self::assertSame($expected->native(), RuntimeOracle::evaluate($runtime)->native(), $source);
        $result = Analysis::session($source, new Configuration(new TargetProfile(floatPrecision: $precision)))->derive(new ReturnQuery('target'));
        self::assertSame([], $result->frontiers, $source);
        self::assertSame([], $result->exceptionalOutcomes, $source);
        self::assertCount(1, $result->normalOutcomes, $source);
        self::assertSame($expected->native(), $result->normalOutcomes[0]->values['return']->native(), $source);
    }
}
