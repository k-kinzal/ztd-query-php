<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\ChangeChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Attribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\OperatorChangeAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\TypeChangeAttribute;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ChangeChecks::class)]
#[Medium]
final class ChangeChecksTest extends TestCase
{
    public function testDeriveReportsEachRefusedAttribute(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql, 'pg-17.2'))->analyze('ALTER OPERATOR = (int4, int4) SET (restrict, negator = NONE, commutator = 1, hashes, sort1 = <)');
        self::assertSame([
            'negator requires a parameter',
            'argument of commutator must be a name',
            'operator attribute "sort1" not recognized',
        ], array_map(static fn ($problem): string => $problem->message(), $operation->facts->diagnostics));
    }

    public function testProblemAcceptsARemovedFunction(): void
    {
        self::assertNull((new ChangeChecks())->problem(new Attribute(new Name('receive'), TypeChangeAttribute::Receive), true, GrammarRelease::PostgreSql172));
    }

    public function testChangeableRefusesTheAttributesAddedInPostgreSql17Before(): void
    {
        $checks = new ChangeChecks();
        self::assertSame([false, true, false], [
            $checks->changeable(OperatorChangeAttribute::Negator, GrammarRelease::PostgreSql166),
            $checks->changeable(OperatorChangeAttribute::Negator, GrammarRelease::PostgreSql172),
            $checks->changeable(TypeChangeAttribute::Element, GrammarRelease::PostgreSql172),
        ]);
    }
}
