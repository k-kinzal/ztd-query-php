<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Name;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpecKind;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(RoleSpec::class)]
#[Small]
final class RoleSpecTest extends TestCase
{
    public function testRenderWritesARoleName(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new RoleSpec(RoleSpecKind::Named, new Name('Admin')))->render($out);
        self::assertSame('"Admin"', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesTheDesignations(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new RoleSpec(RoleSpecKind::Everyone))->render($out);
        self::assertSame('public', (new Lexical())->join($out->pieces()));
        $second = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new RoleSpec(RoleSpecKind::SessionUser))->render($second);
        self::assertSame('SESSION_USER', (new Lexical())->join($second->pieces()));
    }

    public function testRejectsANameOnADesignation(): void
    {
        $this->expectExceptionMessage('A role specification has a name exactly when it names a role.');
        new RoleSpec(RoleSpecKind::CurrentUser, new Name('x'));
    }

    public function testRejectsTheReservedWordNone(): void
    {
        $this->expectExceptionMessage('The words public and none cannot name a role.');
        new RoleSpec(RoleSpecKind::Named, new Name('none'));
    }
}
