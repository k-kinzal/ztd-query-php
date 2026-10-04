<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\ObjectProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\ObjectProblemKind;

#[CoversClass(ObjectProblem::class)]
#[Small]
final class ObjectProblemTest extends TestCase
{
    public function testMessageInsertsTheSubject(): void
    {
        self::assertSame('improper relation name (too many dotted names): a.b.c.d.e', (new ObjectProblem(ObjectProblemKind::ImproperRelation, 'a.b.c.d.e'))->message());
    }
}
