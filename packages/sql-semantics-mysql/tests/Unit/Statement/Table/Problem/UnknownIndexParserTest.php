<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\UnknownIndexParser;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(UnknownIndexParser::class)]
#[Small]
final class UnknownIndexParserTest extends TestCase
{
    public function testMessageIdentifiesTheUnresolvedPlugin(): void
    {
        $problem = new UnknownIndexParser(new Name('CustomParser'));

        self::assertSame("Function 'CustomParser' is not defined", $problem->message());
        self::assertSame('CustomParser', $problem->parser->value);
    }
}
