<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Connection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Connection\Attach;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValueRow;

#[CoversClass(Attach::class)]
#[Small]
final class AttachTest extends TestCase
{
    public function testToStringKeepsTheFilenameNameAndKeyInOrder(): void
    {
        $file = new ValueRow([new TextLiteral(':memory:')]);
        $result = new Attach($file, new Parameter('003'), new ValueRow([new Parameter('007')]));

        self::assertSame("ATTACH (':memory:') AS ?003 KEY (?007)", $result->toString());
    }

    public function testProfileIdIdentifiesThePinnedLanguage(): void
    {
        $result = new Attach(new ValueRow([new TextLiteral(':memory:')]), new Parameter('2'));

        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', $result->profileId());
    }
}
