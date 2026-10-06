<?php

declare(strict_types=1);

namespace Tests\Unit\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Contract\Platforms;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

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
        $output = new Output(Platforms::of('sqlite')->codec((new Semantics(Dialect::Sqlite))->profile()));

        $output->name(new Name('t'), NameUse::Relation)->symbol('.')->name(new Name('order'));

        self::assertSame(['t', '.', '`order`'], array_map(static fn ($piece): string => $piece->text, $output->pieces()));
    }
}
