<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Name;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Name\CurrentUser;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Rendering\PieceKind;

#[CoversClass(CurrentUser::class)]
#[Small]
final class CurrentUserTest extends TestCase
{
    public function testRenderWritesTheKeyword(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new CurrentUser())->render($out);
        $pieces = $out->pieces();

        self::assertCount(1, $pieces);
        self::assertSame(PieceKind::Keyword, $pieces[0]->kind);
        self::assertSame('CURRENT_USER', (new Lexical())->join($pieces));
    }

    public function testLoweredCurrentUserIsOneValueWithOrWithoutParentheses(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $parser = $platform->parser($profile);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $bare = $lowering->users->account($parser->parse('DROP USER CURRENT_USER')->find('user')[0]);
        $called = $lowering->users->account($parser->parse('DROP USER current_user()')->find('user')[0]);

        self::assertInstanceOf(CurrentUser::class, $bare);
        self::assertInstanceOf(CurrentUser::class, $called);
    }
}
