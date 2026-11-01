<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidJsonDocument;

#[CoversClass(InvalidJsonDocument::class)]
#[Medium]
final class InvalidJsonDocumentTest extends TestCase
{
    public function testMessageDescribesTheDocumentTypeWithoutRetainingInputText(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("SELECT JSON_VALUE(1, '$')");
        $problem = $operation->diagnostics()[0];
        self::assertInstanceOf(InvalidJsonDocument::class, $problem);
        self::assertSame('Invalid data type for JSON data in argument 1 to function json_value; a JSON string or JSON type is required.', $problem->message());
    }
}
