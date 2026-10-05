<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\AttributeProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\AttributeProblemKind;

#[CoversClass(AttributeProblem::class)]
#[Small]
final class AttributeProblemTest extends TestCase
{
    public function testMessageInsertsEverySubject(): void
    {
        self::assertSame('invalid argument for internallength: "fixed"', (new AttributeProblem(AttributeProblemKind::InvalidLength, ['internallength', 'fixed']))->message());
    }

    public function testWarningFollowsTheKind(): void
    {
        self::assertSame([true, false], [(new AttributeProblem(AttributeProblemKind::UnrecognizedAggregateAttribute, ['x']))->warning(), (new AttributeProblem(AttributeProblemKind::UnrecognizedCollationAttribute, ['x']))->warning()]);
    }

    public function testRejectsAMissingSubject(): void
    {
        $this->expectExceptionMessage('A problem has one subject for each place in its message.');
        new AttributeProblem(AttributeProblemKind::NotAName);
    }
}
