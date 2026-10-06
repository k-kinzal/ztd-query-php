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
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Rendering\PieceKind;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(AccountName::class)]
#[Small]
final class AccountNameTest extends TestCase
{
    public function testRenderWritesTheUserAndTheHostJoinedByAtWithoutSpaces(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new AccountName(new Name('bob'), new Name('localhost')))->render($out);
        $pieces = $out->pieces();

        self::assertSame([PieceKind::Name, PieceKind::Symbol, PieceKind::Name], array_column($pieces, 'kind'));
        self::assertSame([false, true, true], array_column($pieces, 'glued'));
        self::assertSame('bob@localhost', (new Lexical())->join($pieces));
    }

    public function testRenderQuotesAPartThatNeedsQuoting(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new AccountName(new Name('bo b'), new Name('10.0.%')))->render($out);

        self::assertSame('`bo b`@`10.0.%`', (new Lexical())->join($out->pieces()));
    }

    public function testRenderOmitsAnAbsentHost(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new AccountName(new Name('bob')))->render($out);

        self::assertSame('bob', (new Lexical())->join($out->pieces()));
    }

    public function testLoweredAccountHoldsTheDecodedParts(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $parser = $platform->parser($profile);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $quoted = $lowering->users->account($parser->parse("DROP USER 'bo b'@'10.0.%'")->find('user')[0]);
        $bare = $lowering->users->account($parser->parse('DROP USER bob')->find('user')[0]);
        $role = $lowering->users->account($parser->parse('DROP ROLE r1@h')->find('role')[0]);

        self::assertInstanceOf(AccountName::class, $quoted);
        self::assertSame('bo b', $quoted->user->value);
        self::assertSame('10.0.%', $quoted->host?->value);
        self::assertInstanceOf(AccountName::class, $bare);
        self::assertSame('bob', $bare->user->value);
        self::assertNull($bare->host);
        self::assertInstanceOf(AccountName::class, $role);
        self::assertSame('r1', $role->user->value);
        self::assertSame('h', $role->host?->value);
    }
}
