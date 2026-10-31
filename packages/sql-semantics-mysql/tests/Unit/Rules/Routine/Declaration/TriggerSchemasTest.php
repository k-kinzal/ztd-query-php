<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Routine\Declaration\TriggerSchemas;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(TriggerSchemas::class)]
#[Small]
final class TriggerSchemasTest extends TestCase
{
    /**
     * @return iterable<string, array{?Name, ?Name, bool}>
     */
    public static function providerQualifiers(): iterable
    {
        yield 'current database' => [null, null, true];
        yield 'trigger database' => [new Name('a'), null, true];
        yield 'table database' => [null, new Name('a'), true];
        yield 'same database' => [new Name('a'), new Name('a'), true];
        yield 'server case comparison' => [new Name('Shop'), new Name('shop'), true];
        yield 'Unicode case comparison' => [new Name('Ä'), new Name('ä'), true];
        yield 'different databases' => [new Name('a'), new Name('b'), false];
    }

    #[DataProvider('providerQualifiers')]
    public function testCompatibleSeparatesExplicitContradictionsFromStateDependentNames(?Name $trigger, ?Name $table, bool $expected): void
    {
        self::assertSame($expected, TriggerSchemas::compatible(new QualifiedName(new Name('tr'), $trigger), new TriggerTable(new QualifiedName(new Name('t'), $table))));
    }
}
