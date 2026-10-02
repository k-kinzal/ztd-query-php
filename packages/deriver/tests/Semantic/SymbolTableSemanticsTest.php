<?php

declare(strict_types=1);

namespace Tests\Semantic;

use Deriver\Result\Alternative;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;

/**
 * Unset variables, symbol-table boundaries, and the variables named by undefined and external reads.
 */
#[CoversNothing]
#[Small]
final class SymbolTableSemanticsTest extends TestCase
{
    /**
     * After unset, a variable is undefined whatever an earlier include did; reading it is null with a warning.
     * @param string $body Function body after the include
     * @throws JsonException If fixture observations cannot be encoded
     */
    #[DataProvider('unsetAfterBoundary')]
    public function testUnsetAfterABoundaryLeavesTheVariableUndefined(string $body): void
    {
        $result = Analysis::argument('<?php function sink($value){} function target($p){$x="a";include $p;' . $body . 'sink($x);}');
        self::assertSame([null], array_map(static fn (Alternative $outcome) => $outcome->values['value']->native(), $result->normalOutcomes));
        $warning = Analysis::frontier($result, 'uninitialized-read');
        self::assertNotNull($warning);
        self::assertSame(['variable:x'], $warning->knownDependencies);
    }

    /**
     * @return array<string, array{string}> Bodies that end with an unset variable
     */
    public static function unsetAfterBoundary(): array
    {
        return [
            'unset' => ['unset($x);'],
            'unset twice' => ['unset($x);unset($x);'],
            'assign then unset' => ['$x="b";unset($x);'],
        ];
    }

    /**
     * @throws JsonException If fixture observations cannot be encoded
     */
    public function testALaterBoundaryMayDefineAnUnsetVariableAgain(): void
    {
        $result = Analysis::argument('<?php function sink($value){} function target($p){$x="a";include $p;unset($x);include $p;sink($x);}');
        self::assertSame(['opaque'], array_map(static fn (Alternative $outcome): string => $outcome->values['value']->kind, $result->normalOutcomes));
        self::assertNull(Analysis::frontier($result, 'uninitialized-read'));
    }

    /**
     * @throws JsonException If fixture observations cannot be encoded
     */
    public function testAssignmentAfterUnsetDefinesTheVariable(): void
    {
        $result = Analysis::argument('<?php function sink($value){} function target($p){$x="a";include $p;unset($x);$x="b";sink($x);}');
        self::assertSame(['b'], array_map(static fn (Alternative $outcome) => $outcome->values['value']->native(), $result->normalOutcomes));
        self::assertNull(Analysis::frontier($result, 'uninitialized-read'));
    }

    /**
     * @throws JsonException If fixture observations cannot be encoded
     */
    public function testUnsetOfAnElementAfterABoundaryKeepsTheUnknownArray(): void
    {
        $result = Analysis::argument('<?php function sink($value){} function target($p){$x=["k"=>1];include $p;unset($x["k"]);sink($x);}');
        self::assertNotSame([], $result->normalOutcomes);
        foreach ($result->normalOutcomes as $outcome) {
            self::assertNotSame('constant', $outcome->values['value']->kind);
        }
        self::assertNull(Analysis::frontier($result, 'uninitialized-read'));
    }

    /**
     * @throws JsonException If fixture observations cannot be encoded
     */
    public function testUnsetInScriptScopeRemovesTheGlobalVariable(): void
    {
        $result = Analysis::argument('<?php function sink($value){} $x="a";unset($x);sink($x);');
        self::assertSame([null], array_map(static fn (Alternative $outcome) => $outcome->values['value']->native(), $result->normalOutcomes));
        self::assertSame(['global:x'], Analysis::frontier($result, 'uninitialized-read')?->knownDependencies);
    }

    /**
     * @throws JsonException If fixture observations cannot be encoded
     */
    public function testUnsetInScriptScopeOnlyBreaksAReference(): void
    {
        $result = Analysis::argument('<?php function sink($value){} $y="a";$x=&$y;unset($x);sink($y);');
        self::assertSame(['a'], array_map(static fn (Alternative $outcome) => $outcome->values['value']->native(), $result->normalOutcomes));
        self::assertSame([], $result->frontiers);
    }

    /**
     * @throws JsonException If fixture observations cannot be encoded
     */
    public function testUndefinedReadsNameTheVariable(): void
    {
        $script = Analysis::argument('<?php function sink($value){} sink($sql);');
        self::assertSame([null], array_map(static fn (Alternative $outcome) => $outcome->values['value']->native(), $script->normalOutcomes));
        self::assertSame(['global:sql'], Analysis::frontier($script, 'uninitialized-read')?->knownDependencies);
        $local = Analysis::argument('<?php function sink($value){} function target(){sink($sql);}');
        self::assertSame(['variable:sql'], Analysis::frontier($local, 'uninitialized-read')?->knownDependencies);
    }

    /**
     * @throws JsonException If fixture observations cannot be encoded
     */
    public function testUnconfiguredGlobalReadsNameTheGlobal(): void
    {
        $value = Analysis::argument('<?php function sink($value){} function target(){global $wpdb;sink($wpdb);}');
        self::assertSame(['external'], array_map(static fn (Alternative $outcome): string => $outcome->values['value']->kind, $value->normalOutcomes));
        $external = Analysis::frontier($value, 'global-read');
        self::assertNotNull($external);
        self::assertSame(['EXTERNAL_INPUT', ['global:wpdb']], [$external->code, $external->knownDependencies]);
        self::assertSame('closed', $value->assessment->closure);
        $property = Analysis::argument('<?php function sink($value){} function target(){global $wpdb;sink($wpdb->prefix);}');
        self::assertSame(['object:global:wpdb:prefix'], array_map(static fn (Alternative $outcome) => $outcome->values['value']->literal, $property->normalOutcomes));
        self::assertSame(['global:wpdb'], Analysis::frontier($property, 'global-read')?->knownDependencies);
    }
}
