<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Validation\Correspondence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Expression\Rendering as R;
use SqlSemantics\Statement\Expression\SqliteBinaryOperator;
use SqlSemantics\Statement\Validation\Correspondence\SpellingMatch;

#[CoversClass(SpellingMatch::class)]
#[Small]
final class SpellingMatchTest extends TestCase
{
    public function testSameComparesValidatedValuesIndependentlyOfTheirObjectIdentity(): void
    {
        self::assertTrue(SpellingMatch::same(new R\SqliteCaseLayout(), new R\SqliteCaseLayout()));
        self::assertTrue(SpellingMatch::same(new R\SqliteCaseArmLayout(), new R\SqliteCaseArmLayout()));
        self::assertTrue(SpellingMatch::same(new R\SqliteElseLayout(), new R\SqliteElseLayout()));
        self::assertTrue(SpellingMatch::same(new R\SqliteBinaryLayout(SqliteBinaryOperator::Add, '+'), new R\SqliteBinaryLayout(SqliteBinaryOperator::Add, '+')));
        self::assertTrue(SpellingMatch::same(null, null));
    }

    public function testSameRejectsChangedNameSensitiveLayout(): void
    {
        self::assertFalse(SpellingMatch::same(new R\SqliteCaseLayout(), new R\SqliteCaseLayout('case')));
        self::assertFalse(SpellingMatch::same(new R\SqliteCaseArmLayout(), new R\SqliteCaseArmLayout(afterWhen: '/*test*/')));
        self::assertFalse(SpellingMatch::same(new R\SqliteElseLayout(), new R\SqliteElseLayout(after: '/*else*/')));
        self::assertFalse(SpellingMatch::same(new R\SqliteCaseLayout(), new R\SqliteElseLayout()));
        self::assertFalse(SpellingMatch::same(null, new R\SqliteCaseLayout()));
    }
}
