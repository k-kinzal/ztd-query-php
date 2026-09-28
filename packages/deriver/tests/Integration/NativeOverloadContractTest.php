<?php

declare(strict_types=1);

namespace Tests\Integration;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;

#[CoversNothing]
#[Small]
final class NativeOverloadContractTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testInternalNullCoercionRetainsTheTargetDeprecation(): void
    {
        $result = Analysis::returns('<?php function target(){return [strlen(null), in_array(1,[1],null)];}');
        self::assertSame([0, true], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertSame(['PHP_WARNING', 'PHP_WARNING'], array_column($result->frontiers, 'code'));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testUnsupportedCallableOverloadsRetainNormalValuesAndReferenceEffects(): void
    {
        $result = Analysis::returns('<?php function target(){$name="old"; $result=is_callable("strlen",true,$name);return [$result,$name];}');
        self::assertSame('opaque', $result->normalOutcomes[0]->values['return']->operands[0]->kind);
        self::assertSame('opaque', $result->normalOutcomes[0]->values['return']->operands[1]->kind);
        self::assertContains('UNSUPPORTED_MODEL_CASE', array_column($result->frontiers, 'code'));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testWeakObjectCoercionReportsItsEffectsAndThrowableBoundary(): void
    {
        $result = Analysis::returns('<?php class B{function __toString():string{return "value";}}function pass(string $value){return $value;}function target(){return pass(new B);}');
        self::assertNotEmpty($result->normalOutcomes);
        self::assertContains('UNSUPPORTED_LANGUAGE_FEATURE', array_column($result->frontiers, 'code'));
        self::assertSame('unavailable', $result->assessment->coverage);
        self::assertContains('Throwable', array_map(static fn ($outcome) => $outcome->exception->literal, $result->exceptionalOutcomes));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testAllocationWithADestructorCannotLeaveAnExactKnownReturn(): void
    {
        $result = Analysis::returns('<?php class B{function __destruct(){}}function target(){new B;return 4;}');
        self::assertContains('destructor-lifetime', array_column($result->frontiers, 'operation'));
        self::assertSame('unavailable', $result->assessment->coverage);
        self::assertNotSame('constant', $result->normalOutcomes[0]->values['return']->kind);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testUnknownCallsCannotRestoreAnUnseenStaticDefault(): void
    {
        $result = Analysis::returns('<?php class B{public static int $value=4;}function target(){unknown();return B::$value;}');
        self::assertNotSame('constant', $result->normalOutcomes[0]->values['return']->kind);
        self::assertNotEmpty($result->exceptionalOutcomes);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testUnknownFilterModesKeepTheirCallbackEffectsUnresolved(): void
    {
        $result = Analysis::returns('<?php function target(int $mode){return array_filter([1], fn($x)=>true, $mode);}');
        self::assertContains('UNSUPPORTED_MODEL_CASE', array_column($result->frontiers, 'code'));
        self::assertSame('opaque', $result->normalOutcomes[0]->values['return']->kind);
        self::assertNotEmpty($result->exceptionalOutcomes);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testImplicitConversionsInsideStandardModelsRetainPossibleThrowables(): void
    {
        $result = Analysis::returns('<?php class B{function __toString():string{throw new RuntimeException;}}function target(){return implode(",",[new B]);}');
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->normalOutcomes);
        self::assertNotEmpty($result->exceptionalOutcomes);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testFormattingCannotTreatAnUnconstrainedConversionAsPure(): void
    {
        $result = Analysis::returns('<?php function target($value){return sprintf("%s",$value);}');
        self::assertContains('UNSUPPORTED_MODEL_CASE', array_column($result->frontiers, 'code'));
        self::assertNotEmpty($result->exceptionalOutcomes);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testUnknownSeparatorRetainsTheEmptySeparatorException(): void
    {
        $result = Analysis::returns('<?php function target(string $separator){return explode($separator,"a,b");}');
        self::assertContains('UNSUPPORTED_MODEL_CASE', array_column($result->frontiers, 'code'));
        self::assertNotEmpty($result->normalOutcomes);
        self::assertNotEmpty($result->exceptionalOutcomes);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testUnknownJoinElementsCannotSuppressStringConversionEffects(): void
    {
        $result = Analysis::returns('<?php function target(array $values){return implode(",",$values);}');
        self::assertContains('UNSUPPORTED_MODEL_CASE', array_column($result->frontiers, 'code'));
        self::assertSame('opaque', $result->normalOutcomes[0]->values['return']->kind);
        self::assertNotEmpty($result->exceptionalOutcomes);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testMalformedTrimRangesDoNotEmitWarningsFromTheAnalyzer(): void
    {
        $result = Analysis::returns('<?php function target(){return trim("abc","z..a");}');
        self::assertContains('UNSUPPORTED_MODEL_CASE', array_column($result->frontiers, 'code'));
        self::assertNotEmpty($result->normalOutcomes);
        self::assertNotEmpty($result->exceptionalOutcomes);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testUnknownMergeCannotKeepAnOverwrittenFieldConstant(): void
    {
        $result = Analysis::returns('<?php function target(array $input){return array_merge(["a"=>1],$input)["a"];}');
        self::assertNotSame('constant', $result->normalOutcomes[0]->values['return']->kind);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testUnknownUnpackRetainsItsPrefixAndSubsequentWritesSymbolically(): void
    {
        $result = Analysis::returns('<?php function target(array $input){return ["a"=>1,...$input,"b"=>2];}');
        self::assertSame('array-set', $result->normalOutcomes[0]->values['return']->kind);
        self::assertSame('array-merge', $result->normalOutcomes[0]->values['return']->operands[0]->kind);
        self::assertSame('Error', $result->exceptionalOutcomes[0]->exception->literal);
        self::assertContains('WIDENED', array_column($result->frontiers, 'code'));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testSymbolicCountableKeepsUserCodeEffectsAndThrowables(): void
    {
        $result = Analysis::returns('<?php function target(Countable $input){return count($input);}');
        self::assertContains('UNSUPPORTED_MODEL_CASE', array_column($result->frontiers, 'code'));
        self::assertNotEmpty($result->exceptionalOutcomes);
    }

}
