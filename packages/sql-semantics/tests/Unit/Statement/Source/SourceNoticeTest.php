<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Source\SourceNotice;

#[CoversClass(SourceNotice::class)]
#[Small]
final class SourceNoticeTest extends TestCase
{
    public function testSubjectKeepsTheDecodedNameIndependentOfTheWarning(): void
    {
        $name = new Name('full');
        $warning = new Deprecation(Deprecated::UnquotedFull);
        $notice = new SourceNotice($name, $warning, 16);

        self::assertSame($name, $notice->subject);
        self::assertSame($warning, $notice->warning);
        self::assertSame(16, $notice->offset);
    }
}
