<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Dml\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Lowering\Dml\Insert\SyntaxDeprecations;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;

#[CoversClass(SyntaxDeprecations::class)]
#[Small]
final class SyntaxDeprecationsTest extends TestCase
{
    public function testCompletedDistinguishesCommandsAndReleaseVocabulary(): void
    {
        $rules = new SyntaxDeprecations();
        $insert = new Node('insert_lock_option', 0, [new Token(0, 'DELAYED_SYM', 'DELAYED', 7)]);
        $replace = new Node('replace_lock_option', 0, [new Token(0, 'DELAYED_SYM', 'DELAYED', 8)]);

        self::assertSame(Deprecated::DelayedInsert, $rules->completed($insert, GrammarRelease::MySql5651));
        self::assertSame(Deprecated::InsertDelayed, $rules->completed($insert, GrammarRelease::MySql5744));
        self::assertSame(Deprecated::DelayedReplace, $rules->completed($replace, GrammarRelease::MySql5651));
        self::assertSame(Deprecated::ReplaceDelayed, $rules->completed($replace, GrammarRelease::MySql847));
        self::assertNull($rules->completed(new Node('ident', 0, $insert->children), GrammarRelease::MySql847));
        self::assertNull($rules->completed(new Node('insert_lock_option', 0, []), GrammarRelease::MySql847));
    }
}
