<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Problem\AccessProblem::class)]
#[Medium]
final class AccessProblemTest extends TestCase
{
    public function testMessageNamesTheSubjects(): void
    {
        self::assertSame(['invalid privilege type EXECUTE for schema'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT EXECUTE ON SCHEMA s TO joe')->facts->diagnostics));
    }

    public function testMessageWithoutSubject(): void
    {
        self::assertSame('conflicting or redundant options', (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Problem\AccessProblem(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Problem\AccessProblemRule::RedundantOptions))->message());
    }

    public function testRejectsAMissingSubject(): void
    {
        $this->expectExceptionMessage('An access problem names one subject for each place of its message.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Problem\AccessProblem(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Problem\AccessProblemRule::ReservedRoleName);
    }
}
