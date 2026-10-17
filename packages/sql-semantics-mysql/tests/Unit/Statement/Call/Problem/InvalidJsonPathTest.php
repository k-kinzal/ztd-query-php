<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidJsonPath;

#[CoversClass(InvalidJsonPath::class)]
#[Medium]
final class InvalidJsonPathTest extends TestCase
{
    public function testMessageDescribesTheDecodedPathPosition(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("SELECT JSON_VALUE(NULL, 'bad')");
        $problem = $operation->facts->diagnostics[0];
        self::assertInstanceOf(InvalidJsonPath::class, $problem);
        self::assertSame('Invalid JSON path expression. The error is around character position 1.', $problem->message());
    }
}
