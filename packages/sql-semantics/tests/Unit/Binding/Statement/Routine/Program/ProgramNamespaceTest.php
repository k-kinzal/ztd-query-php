<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Routine\Program\ProgramNamespace;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\LocalVariable;
use SqlSemantics\Model\Definition\Routine\Stored\DeclaredDomain;
use SqlSemantics\Model\Scalar\Reference\LocalVariableReference;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(ProgramNamespace::class)]
#[Medium]
final class ProgramNamespaceTest extends TestCase
{
    public function testDeclareShadowsAnEarlierVariable(): void
    {
        $outer = new LocalVariable('a', new DeclaredDomain(TypeDescriptor::builtin(Dialect::MySql, 'integer')));
        $inner = new LocalVariable('A', new DeclaredDomain(TypeDescriptor::builtin(Dialect::MySql, 'text')));
        $names = (new ProgramNamespace())->declare([$outer])->declare([$inner]);
        self::assertSame($inner, $names->variable('a'));
    }

    public function testVariableIgnoresCase(): void
    {
        $variable = new LocalVariable('Total', new DeclaredDomain(TypeDescriptor::builtin(Dialect::MySql, 'integer')));
        self::assertSame($variable, (new ProgramNamespace())->declare([$variable])->variable('TOTAL'));
        self::assertNull((new ProgramNamespace())->variable('total'));
    }

    public function testResolveReturnsLocalVariablesAndLeavesOtherNames(): void
    {
        $variable = new LocalVariable('a', new DeclaredDomain(TypeDescriptor::builtin(Dialect::MySql, 'integer')));
        $names = (new ProgramNamespace())->declare([$variable]);
        $scope = new Scope(new Identifiers(Dialect::MySql));
        $token = new Token(0, 'IDENT', 'a', 0);
        self::assertInstanceOf(LocalVariableReference::class, $names->resolve($scope, ['a'], $token));
        self::assertNull($names->resolve($scope, ['t', 'a'], $token));
        self::assertNull($names->resolve($scope, ['b'], $token));
    }

    public function testLookupIsNullOutsideStoredPrograms(): void
    {
        $scope = new Scope(new Identifiers(Dialect::MySql));
        self::assertNull(ProgramNamespace::lookup($scope, ['a'], new Token(0, 'IDENT', 'a', 0)));
    }

    public function testResolveDiagnosesARowTheTriggerEventLacks(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramObject->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (n INT)')))->bind('CREATE TRIGGER tr AFTER DELETE ON t FOR EACH ROW DO NEW.n', strict: false);
    }
}
