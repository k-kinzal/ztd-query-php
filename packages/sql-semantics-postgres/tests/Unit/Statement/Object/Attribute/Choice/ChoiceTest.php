<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object\Attribute\Choice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\Alignment;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\Choice;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\CollationProvider;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\FinalModification;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\Parallelism;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\StorageStrategy;

#[CoversClass(Choice::class)]
#[Small]
final class ChoiceTest extends TestCase
{
    public function testReadAnswersTheMemberOrNull(): void
    {
        self::assertSame([Alignment::Int, null], [Alignment::read('INT4'), StorageStrategy::read('int4')]);
    }

    public function testEveryWordSetIsAChoice(): void
    {
        self::assertContains(Choice::class, class_implements(Alignment::class));
        self::assertContains(Choice::class, class_implements(CollationProvider::class));
        self::assertContains(Choice::class, class_implements(FinalModification::class));
        self::assertContains(Choice::class, class_implements(Parallelism::class));
        self::assertContains(Choice::class, class_implements(StorageStrategy::class));
    }
}
