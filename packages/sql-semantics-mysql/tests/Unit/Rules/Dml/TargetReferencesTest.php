<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Dml\TargetReferences;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(TargetReferences::class)]
#[Small]
final class TargetReferencesTest extends TestCase
{
    public function testNamesKeepsAliasesAndUnaliasedDatabaseQualification(): void
    {
        $table = new TableReference(new QualifiedName(new Name('t'), new Name('d')));
        $alias = new TableReference(new QualifiedName(new Name('u')), new Name('x'));

        self::assertEquals([$table->name, new QualifiedName(new Name('x'))], (new TargetReferences())->names([$table, $alias]));
    }

    public function testMissingFindsTheFirstDisconnectedTarget(): void
    {
        $t = new QualifiedName(new Name('t'));
        $x = new QualifiedName(new Name('x'));
        $rules = new TargetReferences();

        self::assertSame($x, $rules->missing([$t, $x], [new TableReference($t)]));
        self::assertNull($rules->missing([$t], [new TableReference($t)]));
        self::assertSame($t, $rules->missing([$t], [new TableReference($t, new Name('alias'))]));
    }

    public function testAdmitsLeavesDatabaseSelectionAndCaseToResolution(): void
    {
        $t = new QualifiedName(new Name('t'));
        $qualified = new QualifiedName(new Name('T'), new Name('d'));
        $rules = new TargetReferences();

        self::assertTrue($rules->admits($qualified, new TableReference($t)));
        self::assertTrue($rules->admits($t, new TableReference($qualified)));
        self::assertFalse($rules->admits($qualified, new TableReference(new QualifiedName(new Name('T'), new Name('other')))));
        self::assertFalse($rules->admits($qualified, new TableReference($t, new Name('T'))));
        self::assertTrue($rules->admits($t, new TableReference($qualified, new Name('T'))));
    }

    public function testOccurrencesStopsAtDerivedQueryBoundaries(): void
    {
        $table = new TableReference(new QualifiedName(new Name('hidden')));
        $derived = new \SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\Star()], new TableReference(new QualifiedName(new Name('hidden')))), new Name('visible'));
        $nested = new \SqlSemantics\Platform\MySql\Statement\Relation\NestedRelation(new \SqlSemantics\Platform\MySql\Statement\Relation\TableList([$table, $derived]));
        $rules = new TargetReferences();

        self::assertSame([$table, $derived], $rules->occurrences([$nested]));
        self::assertSame($table->name, $rules->missing([$table->name], [$derived]));
        self::assertNull($rules->missing([new QualifiedName(new Name('visible'))], [$derived]));
    }
}
