<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\ColumnDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\KeywordDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\TypeKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeDesignation;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(TypeDesignation::class)]
#[Small]
final class TypeDesignationTest extends TestCase
{
    public function testEverySpellingOfATypeIsADesignation(): void
    {
        self::assertContains(TypeDesignation::class, class_implements(KeywordDesignation::class));
        self::assertContains(TypeDesignation::class, class_implements(NamedDesignation::class));
        self::assertContains(TypeDesignation::class, class_implements(ColumnDesignation::class));
    }

    public function testTypeFactIsTheTypeTheDesignationDenotesInAContext(): void
    {
        $context = (new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true);
        $designation = new KeywordDesignation(TypeKeyword::Bigint);
        self::assertEquals(new Known(Builtin::Int8), $designation->typeFact($context, false));
    }

    public function testCatalogNameIsTheNameAnUnaliasedTypedConstantGetsAsAColumn(): void
    {
        self::assertSame('int8', (new KeywordDesignation(TypeKeyword::Bigint))->catalogName()->value);
    }
}
