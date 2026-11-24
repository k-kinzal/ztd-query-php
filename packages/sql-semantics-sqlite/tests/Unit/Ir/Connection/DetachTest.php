<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Connection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Connection\AttachmentName;
use SqlSemantics\Platform\Sqlite\Ir\Connection\Detach;
use SqlSemantics\Platform\Sqlite\Ir\Connection\DetachTarget;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;

#[CoversClass(Detach::class)]
#[Small]
final class DetachTest extends TestCase
{
    public function testToStringPreservesTheNativeNameOrParameter(): void
    {
        $target = new DetachTarget(new Identifier('alpha'), new AttachmentName(new TextLiteral('ALPHA')));

        self::assertSame("DETACH 'ALPHA'", (new Detach($target))->toString());
        self::assertSame('DETACH ?02', (new Detach(new Parameter('02')))->toString());
    }

    public function testProfileIdIdentifiesThePinnedLanguage(): void
    {
        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', (new Detach(new Parameter('2')))->profileId());
    }
}
