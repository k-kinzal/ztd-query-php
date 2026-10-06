<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\Alignment;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\Parallelism;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;

#[CoversClass(Reading::class)]
#[Small]
final class ReadingTest extends TestCase
{
    public function testKindsListsTheObjectsANameCanStandFor(): void
    {
        self::assertSame([ObjectKind::Function, ObjectKind::Operator, ObjectKind::OperatorClass, ObjectKind::Collation, ObjectKind::TextSearchParser, ObjectKind::TextSearchTemplate, ObjectKind::TextSearchConfiguration, ObjectKind::Type], Reading::kinds());
    }

    public function testKindAnswersTheObjectANameReadingLooksUp(): void
    {
        self::assertSame([ObjectKind::Function, ObjectKind::Type, null, null], [Reading::Function->kind(), Reading::CreatedType->kind(), Reading::Type->kind(), Reading::Ignored->kind()]);
    }

    public function testChoicesAnswersTheWordSetOfAChoiceReading(): void
    {
        self::assertSame([Parallelism::class, Alignment::class, null], [Reading::Parallelism->choices(), Reading::Alignment->choices(), Reading::Text->choices()]);
    }
}
