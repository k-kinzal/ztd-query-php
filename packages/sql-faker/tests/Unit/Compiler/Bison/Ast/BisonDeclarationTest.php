<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Compiler\Bison\Ast;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Compiler\Bison\Ast\BisonAst;
use SqlFaker\Compiler\Bison\Ast\BisonDefineDeclaration;
use SqlFaker\Compiler\Bison\Ast\BisonExpectDeclaration;
use SqlFaker\Compiler\Bison\Ast\BisonParamDeclaration;
use SqlFaker\Compiler\Bison\Ast\BisonPrecedenceDeclaration;
use SqlFaker\Compiler\Bison\Ast\BisonStartDeclaration;
use SqlFaker\Compiler\Bison\Ast\BisonTokenDeclaration;
use SqlFaker\Compiler\Bison\Ast\BisonTokenDefinition;
use SqlFaker\Compiler\Bison\Ast\BisonTypeDeclaration;
use SqlFaker\Compiler\Bison\Ast\BisonUnknownDeclaration;

#[CoversNothing]
final class BisonDeclarationTest extends TestCase
{
    public function testEveryDeclarationKindCanBeCarriedByAnAst(): void
    {
        $declarations = [
            new BisonStartDeclaration('statement'),
            new BisonExpectDeclaration(3),
            new BisonDefineDeclaration('api.pure', null),
            new BisonParamDeclaration('parse-param', 'THD *thd'),
            new BisonPrecedenceDeclaration('left', null, ['OR_SYM']),
            new BisonTokenDeclaration('<lexer>', [new BisonTokenDefinition('SELECT_SYM', null, null)]),
            new BisonTypeDeclaration('<num>', ['expr']),
            new BisonUnknownDeclaration('%pure-parser', ''),
        ];

        $ast = new BisonAst('statement', null, $declarations, [], null);

        self::assertSame($declarations, $ast->declarations);
    }
}
