<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Access;

use MySqlMemory\Command\Access\HandlerKeys;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(HandlerKeys::class)]
#[Small]
final class HandlerKeysTest extends TestCase
{
    public function testScopePlacesTheOriginalHandlerIdentityAndDefinition(): void
    {
        $instance = new Instance();
        $instance->connect()->query('CREATE DATABASE d; CREATE TABLE d.t(a INT)');
        $table = $instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $handler = new M\Dml\Handler\OpenHandler(new Name('h'));
        $scope = (new HandlerKeys())->scope($handler, $table);
        self::assertSame($table->definition, $scope->tables[spl_object_id($handler)]);
        self::assertSame(['a'], $scope->names[spl_object_id($handler)]);
    }

    public function testCheckRefusesAColumnOnlyAfterItsCallerHasResolvedIt(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1210);
        (new HandlerKeys())->check(new M\Name\ColumnUse(new Name('a')), GrammarRelease::MySql847);
    }

    public function testDependentPreservesDefaultsAndAssignmentValueDependencies(): void
    {
        $keys = new HandlerKeys();
        $column = new M\Name\ColumnUse(new Name('a'));
        self::assertFalse($keys->dependent(new M\Expression\Access\DefaultOfColumn($column), GrammarRelease::MySql847));
        self::assertFalse($keys->dependent(new M\Expression\Access\InsertedColumn($column), GrammarRelease::MySql847));
        self::assertFalse($keys->dependent(new M\Variable\VariableAssignment(new M\Variable\UserVariable(new Name('x')), new M\Literal\NumberLiteral('1')), GrammarRelease::MySql847));
        self::assertTrue($keys->dependent(new M\Variable\VariableAssignment(new M\Variable\UserVariable(new Name('x')), $column), GrammarRelease::MySql847));
        self::assertFalse($keys->dependent(new M\Variable\UserVariable(new Name('x')), GrammarRelease::MySql5744));
        self::assertTrue($keys->dependent(new M\Variable\UserVariable(new Name('x')), GrammarRelease::MySql847));
    }

    public function testSessionSeparatesExecutionBoundReadsFromPerCallFunctions(): void
    {
        $keys = new HandlerKeys();
        self::assertTrue($keys->session(new M\Call\FunctionCall(new Name('CONNECTION_ID'), [])));
        $timestamp = new M\Call\FunctionCall(new Name('UNIX_TIMESTAMP'), []);
        self::assertFalse($keys->session($timestamp, GrammarRelease::MySql8044));
        self::assertTrue($keys->session($timestamp, GrammarRelease::MySql847));
        self::assertFalse($keys->session(new M\Call\FunctionCall(new Name('RAND'), [new M\Call\CallArgument(new M\Literal\NumberLiteral('1'))])));
        self::assertFalse($keys->session(new M\Call\FunctionCall(new Name('UNIX_TIMESTAMP'), [new M\Call\CallArgument(new M\Literal\NumberLiteral('0'))])));
    }
}
