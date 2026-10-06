<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Operation;

use Deriver\Result\Alternative;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;

#[CoversNothing]
#[Small]
final class PartialFormattingTest extends TestCase
{
    /**
     * @throws JsonException If fixture values cannot be encoded
     */
    public function testApplyKeepsTheFormattedKnownPrefixBeforeAnExplicitResidual(): void
    {
        $result = Analysis::returns('<?php function target(string $f){return sprintf("SELECT %s FROM t WHERE ".$f,"id");}');
        self::assertCount(1, $result->normalOutcomes);
        $value = $result->normalOutcomes[0]->values['return'];
        self::assertSame('concat', $value->kind);
        self::assertSame('SELECT id FROM t WHERE ', $value->operands[0]->native());
        self::assertSame(['UNSUPPORTED_MODEL_CASE'], array_column($result->frontiers, 'code'));
        self::assertSame('sprintf', $result->frontiers[0]->operation);
        self::assertTrue($result->exceptionalOutcomes[0]->exception->attributes['uncertain'] ?? false);
    }

    /**
     * @throws JsonException If fixture values cannot be encoded
     */
    public function testApplyFormatsVectorArgumentsInValueOrder(): void
    {
        $result = Analysis::returns('<?php function target(string $f){return vsprintf(\'%2$s %1$s \'.$f,["a"=>"x","b"=>"y"]);}');
        self::assertSame('y x ', $result->normalOutcomes[0]->values['return']->operands[0]->native());
    }

    /**
     * @throws JsonException If fixture values cannot be encoded
     */
    public function testApplyLeavesUndecidablePrefixesToTheOrdinaryModel(): void
    {
        $result = Analysis::returns('<?php function target(string $f){return sprintf("%s %s ".$f,"id");}');
        $values = array_map(static fn (Alternative $outcome) => $outcome->values['return'], $result->normalOutcomes);
        self::assertSame(['opaque'], array_values(array_unique(array_map(static fn ($value): string => $value->kind, $values))));
        self::assertSame(['UNSUPPORTED_MODEL_CASE'], array_column($result->frontiers, 'code'));
    }

    /**
     * @throws JsonException If fixture values cannot be encoded
     */
    public function testApplyDoesNotChangeCompletelyKnownFormats(): void
    {
        $result = Analysis::returns('<?php function target(){$f=" %s";return sprintf("%s".$f,"a","b");}');
        self::assertSame('a b', $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }
}
