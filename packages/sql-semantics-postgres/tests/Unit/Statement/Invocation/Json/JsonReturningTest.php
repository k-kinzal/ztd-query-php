<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Json;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonFormat;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonReturning;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(JsonReturning::class)]
#[Small]
final class JsonReturningTest extends TestCase
{
    public function testDeriveClauseDerivesTheTypeModifiers(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new JsonReturning(new TypeName(new NamedDesignation(new DottedName([new Name('text')])))))->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesTheTypeAndFormat(): void
    {
        $returning = new JsonReturning(new TypeName(new NamedDesignation(new DottedName([new Name('text')]))), new JsonFormat());
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $returning->render($out);
        self::assertSame('RETURNING text FORMAT JSON', (new Lexical())->join($out->pieces()));
    }
}
