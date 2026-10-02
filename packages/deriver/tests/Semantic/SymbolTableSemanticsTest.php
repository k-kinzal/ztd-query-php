<?php

declare(strict_types=1);

namespace Tests\Semantic;

use Deriver\Result\Alternative;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;

/**
 * Variables named by undefined and external reads.
 */
#[CoversNothing]
#[Small]
final class SymbolTableSemanticsTest extends TestCase
{
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
