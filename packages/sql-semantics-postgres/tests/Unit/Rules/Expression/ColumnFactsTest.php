<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\ColumnFacts;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\AmbiguousOutputName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(ColumnFacts::class)]
#[Small]
final class ColumnFactsTest extends TestCase
{
    public function testDeriveRefersToAnOutputColumnByName(): void
    {
        $context = (new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true);
        $field = new Field(0, new OutputSlot(new Name('x'), new Known(Builtin::Int4), Nullability::NotNull));
        $fact = (new ColumnFacts())->derive(new Derivation($context), new Environment($context, null, [], [], [$field]), [new Name('x')]);
        self::assertEquals(new AliasTarget($field), $fact->resolution);
        self::assertEquals(new Known(Builtin::Int4), $fact->type);
    }

    public function testAliasReportsSeveralOutputColumns(): void
    {
        $context = (new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true);
        $field = new Field(0, new OutputSlot(new Name('x'), new Known(Builtin::Int4), Nullability::NotNull));
        $other = new Field(1, new OutputSlot(new Name('x'), new Known(Builtin::Text), Nullability::NotNull));
        $fact = (new ColumnFacts())->alias(new Derivation($context), new Environment($context, null, [], [], [$field, $other]), new Name('x'));
        self::assertInstanceOf(Invalid::class, $fact->type);
        self::assertInstanceOf(AmbiguousOutputName::class, $fact->type->cause);
    }
}
