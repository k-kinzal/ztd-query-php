<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Missing\UndeclaredDomain;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Dependent;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Invalid;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\NullOnly;
use SqlSemantics\Platform\PostgreSql\Rules\Typing\DeclaredTyping;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\ArrayOf;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\NamedOnPath;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Undetermined;

#[CoversClass(DeclaredTyping::class)]
#[Small]
final class DeclaredTypingTest extends TestCase
{
    public function testFactKnowsACatalogType(): void
    {
        $type = new ArrayOf(Builtin::Int4);
        $fact = (new DeclaredTyping())->fact($type);
        self::assertInstanceOf(Known::class, $fact);
        self::assertSame($type, $fact->descriptor);
    }

    public function testFactDependsOnTheMissingInputsOfATypeKnownByNameOnly(): void
    {
        $missing = new UndeclaredDomain(new QualifiedName(new Name('mood')));
        $fact = (new DeclaredTyping())->fact(new ArrayOf(new NamedOnPath(new QualifiedName(new Name('mood')), [$missing])));
        self::assertInstanceOf(Dependent::class, $fact);
        self::assertSame([$missing], $fact->missing);
    }

    public function testFactDependsOnTheMissingInputsOfAnUndeterminedType(): void
    {
        $missing = new UndeclaredDomain(new QualifiedName(new Name('mood')));
        $fact = (new DeclaredTyping())->fact(new Undetermined([$missing]));
        self::assertInstanceOf(Dependent::class, $fact);
        self::assertSame([$missing], $fact->missing);
    }

    public function testDefinedResolvesUnknownAndNullToText(): void
    {
        $types = new DeclaredTyping();
        self::assertSame([Builtin::Text, Builtin::Text, Builtin::Int4], [$types->defined(new Known(Builtin::Unknown)), $types->defined(new NullOnly()), $types->defined(new Known(Builtin::Int4))]);
    }

    public function testDefinedKeepsTheMissingInputsOfADependentOutput(): void
    {
        $missing = new UndeclaredDomain(new QualifiedName(new Name('mood')));
        $type = (new DeclaredTyping())->defined(new Dependent([$missing]));
        self::assertInstanceOf(Undetermined::class, $type);
        self::assertSame([$missing], $type->missing);
    }

    public function testDefinedHasNoTypeForAnInvalidOutput(): void
    {
        self::assertNull((new DeclaredTyping())->defined(new Invalid(new \SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionProblem(\SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionRule::DuplicateColumn, new Name('a')))));
    }
}
