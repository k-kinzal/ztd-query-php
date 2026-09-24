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

    public function testResolveReadsTriggerRowColumnsInAnyCase(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (a INT, n INT)')))->bind('CREATE TRIGGER tr BEFORE UPDATE ON t FOR EACH ROW SET NEW.n = OLD.a + 1', strict: false);
        self::assertSame([], $statement->diagnostics);
        self::assertSame('CREATE TRIGGER `tr` BEFORE UPDATE ON `t` FOR EACH ROW SET `new`.`n` = (`old`.`a` + 1)', $statement->toString());
    }

    public function testResolveDiagnosesAnUnknownTriggerColumn(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (a INT, n INT)')))->bind('CREATE TRIGGER tr BEFORE UPDATE ON t FOR EACH ROW SET NEW.n = old.x', strict: false);
        self::assertCount(1, $statement->diagnostics);
        self::assertSame('unknown-column', $statement->diagnostics[0]->reason);
        self::assertSame('Cannot resolve trigger column: old.x', $statement->diagnostics[0]->message);
    }

    /**
     * @param list<string> $parts
     */
    #[\PHPUnit\Framework\Attributes\TestWith([['new', 'n'], false])]
    #[\PHPUnit\Framework\Attributes\TestWith([['new'], true])]
    #[\PHPUnit\Framework\Attributes\TestWith([['x', 'n'], true])]
    #[\PHPUnit\Framework\Attributes\TestWith([['new', 'n', 'x'], true])]
    public function testResolveLeavesNamesOutsideTheTriggerRows(array $parts, bool $trigger): void
    {
        self::assertNull((new ProgramNamespace([], $trigger))->resolve(new Scope(new Identifiers(Dialect::MySql)), $parts, new Token(0, 'IDENT', 'n', 0)));
    }

    public function testLookupIsNullWithoutAProgramNamespace(): void
    {
        $context = new \SqlSemantics\Binding\Query\QueryContext(new \SqlSemantics\Binding\TableResolver((new SchemaBuilder(Dialect::MySql))->build(), new Identifiers(Dialect::MySql), ''));
        self::assertNull(ProgramNamespace::lookup(new Scope(new Identifiers(Dialect::MySql), queries: $context), ['a'], new Token(0, 'IDENT', 'a', 0)));
    }

    public function testResolveTreatsANamespaceAsOutsideATriggerByDefault(): void
    {
        self::assertNull((new ProgramNamespace())->resolve(new Scope(new Identifiers(Dialect::MySql)), ['new', 'n'], new Token(0, 'IDENT', 'n', 0)));
    }
}
