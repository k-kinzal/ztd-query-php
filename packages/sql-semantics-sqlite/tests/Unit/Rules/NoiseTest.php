<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Rules\Noise;

#[CoversClass(Noise::class)]
#[Small]
final class NoiseTest extends TestCase
{
    public function testPositionsListTheCommandTerminators(): void
    {
        $positions = Noise::positions();

        self::assertSame([0], $positions['ecmd: SEMI']);
        self::assertSame([1], $positions['ecmd: cmdx SEMI']);
        self::assertSame([2], $positions['ecmd: explain cmdx SEMI']);
    }

    public function testPositionsListTheOptionalAsBeforeAnAlias(): void
    {
        self::assertSame([0], Noise::positions()['as: AS nm']);
        self::assertArrayNotHasKey('as: ids', Noise::positions());
    }

    public function testPositionsListTheAssignmentSignOfEverySetlistForm(): void
    {
        $positions = Noise::positions();

        self::assertSame([3], $positions['setlist: setlist COMMA nm EQ expr']);
        self::assertSame([5], $positions['setlist: setlist COMMA LP idlist RP EQ expr']);
        self::assertSame([1], $positions['setlist: nm EQ expr']);
        self::assertSame([3], $positions['setlist: LP idlist RP EQ expr']);
        self::assertCount(8, $positions);
    }

    public function testSynonymsFoldTemporaryIntoTemp(): void
    {
        self::assertSame(['TEMP' => 'TEMP'], Noise::synonyms());
    }
}
