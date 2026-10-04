<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Invocation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Rules\Invocation\Coercions;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\ArrayOf;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Parameterized;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(Coercions::class)]
#[Small]
final class CoercionsTest extends TestCase
{
    public function testKeyAnswersTheLookupType(): void
    {
        $coercions = new Coercions();
        self::assertSame(['unknown', 'int4', null], [$coercions->key(new NullOnly()), $coercions->key(new Known(Builtin::Int4)), $coercions->key(new Dependent([new UndeclaredRoutine(new QualifiedName(new Name('f')))]))]);
    }

    public function testDescriptorDropsModifiersAndNamesArrays(): void
    {
        $coercions = new Coercions();
        self::assertSame(['varchar', 'int4[]'], [$coercions->descriptor(new Parameterized(Builtin::Varchar, 4)), $coercions->descriptor(new ArrayOf(Builtin::Int4))]);
    }

    public function testImplicitFollowsTheCastTable(): void
    {
        $coercions = new Coercions();
        self::assertSame([true, false, true, true], [$coercions->implicit('int4', 'numeric'), $coercions->implicit('numeric', 'int4'), $coercions->implicit('unknown', 'date'), $coercions->implicit('xml', 'any')]);
    }

    public function testTypeAnswersTheDescriptorOfAName(): void
    {
        self::assertEquals(new ArrayOf(Builtin::Text), (new Coercions())->type('text[]'));
    }
}
