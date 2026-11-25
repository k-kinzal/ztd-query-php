<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Exchange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange\PhysicalKeyNames;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Testing\MySql\NativeAdditionFixture;

#[CoversClass(PhysicalKeyNames::class)]
#[Small]
final class PhysicalKeyNamesTest extends TestCase
{
    public function testAllocateUsesActionOrderAndFreesReplacedPhysicalNames(): void
    {
        $owner = NativeAdditionFixture::owner('child');
        $prior = NativeAdditionFixture::key($owner, 'code', IndexRole::Ordinary, 'code');
        $code = $prior->identity->name;
        $names = (new PhysicalKeyNames())->allocate(
            ['code' => $prior],
            ['code' => $code],
            [5 => [$code, null], 2 => [new Identifier('spare'), $code]],
            GrammarRelease::MySql910
        );

        self::assertSame([2, 5], array_keys($names));
        self::assertSame(['code', 'code_2'], array_column($names, 'value'));
    }
}
