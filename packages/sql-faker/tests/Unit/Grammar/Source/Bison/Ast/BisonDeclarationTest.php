<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Source\Bison\Ast;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Source\Bison\Ast\BisonAst;
use SqlFaker\Grammar\Source\Bison\Ast\BisonDefineDeclaration;
use SqlFaker\Grammar\Source\Bison\Ast\BisonExpectDeclaration;
use SqlFaker\Grammar\Source\Bison\Ast\BisonParamDeclaration;
use SqlFaker\Grammar\Source\Bison\Ast\BisonPrecedenceDeclaration;
use SqlFaker\Grammar\Source\Bison\Ast\BisonStartDeclaration;
use SqlFaker\Grammar\Source\Bison\Ast\BisonTokenDeclaration;
use SqlFaker\Grammar\Source\Bison\Ast\BisonTokenDefinition;
use SqlFaker\Grammar\Source\Bison\Ast\BisonTypeDeclaration;
use SqlFaker\Grammar\Source\Bison\Ast\BisonUnknownDeclaration;

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
