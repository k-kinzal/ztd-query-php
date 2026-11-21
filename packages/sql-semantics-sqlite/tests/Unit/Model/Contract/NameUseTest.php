<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Contract\NameUse;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Platform;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(NameUse::class)]
#[Medium]
final class NameUseTest extends TestCase
{
    public function testEveryPositionANameIsSpelledForIsACase(): void
    {
        self::assertSame(['Column', 'Relation', 'Qualifier', 'Alias', 'Routine', 'Label', 'Identifier'], array_map(static fn (NameUse $use): string => $use->name, NameUse::cases()));
    }

    public function testThePositionIsHandedToTheCodec(): void
    {
        $output = new Output(Platform::shared()->codec((new Semantics())->profile()));

        $output->name(new Name('t'), NameUse::Relation)->symbol('.')->name(new Name('order'));

        self::assertSame(['t', '.', '`order`'], array_map(static fn ($piece): string => $piece->text, $output->pieces()));
    }
}
