<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Problem\UtilityProblem::class)]
#[Small]
final class UtilityProblemTest extends TestCase
{
    public function testMessageFillsTheSubjectsIn(): void
    {
        self::assertSame('unrecognized VACUUM option "fast"', (new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Problem\UtilityProblem(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Problem\UtilityProblemKind::UnknownOption, ['VACUUM', 'fast']))->message());
    }

    public function testMessageOfARuleWithoutSubjects(): void
    {
        self::assertSame('no inline code specified', (new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Problem\UtilityProblem(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Problem\UtilityProblemKind::NoInlineCode))->message());
    }

    public function testSubjectCountMustFitTheMessage(): void
    {
        $this->expectException(\SqlSemantics\Diagnostic\InvalidConstruction::class);
        $this->expectExceptionMessage('A utility problem names one subject for each place of its message.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Problem\UtilityProblem(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Problem\UtilityProblemKind::UnknownOption, ['VACUUM']);
    }
}
