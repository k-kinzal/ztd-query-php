<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Ast;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\Bison\Ast\BisonAst;
use SqlFaker\MySql\Bison\Ast\BisonDefineDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonExpectDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonParamDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonPrecedenceDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonStartDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonTokenDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonTypeDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonUnknownDeclaration;

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
            new BisonTokenDeclaration('<lexer>', ['SELECT_SYM']),
            new BisonTypeDeclaration('<num>', ['expr']),
            new BisonUnknownDeclaration('%pure-parser', ''),
        ];

        $ast = new BisonAst('statement', null, $declarations, [], null);

        self::assertSame($declarations, $ast->declarations);
    }
}
