<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\XmlProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\XmlProblemKind;

#[CoversClass(XmlProblem::class)]
#[Small]
final class XmlProblemTest extends TestCase
{
    public function testMessageNamesTheSubject(): void
    {
        self::assertSame('cannot cast XMLSERIALIZE result to integer', (new XmlProblem(XmlProblemKind::SerializeTarget, 'integer'))->message());
    }
}
