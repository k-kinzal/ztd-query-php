<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Analysis\Relations;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect as MySqlDialect;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSqlDialect;
use SqlSemantics\Statement\Model\Sqlite\Value\NmWithIdj_a2015ecf as Name;
use SqlSemantics\Statement\Reference;
use SqlSemantics\Statement\ReferenceKind;
use Tests\Contract\Resolved;

#[CoversClass(Relations::class)]
#[UsesClass(Semantics::class)]
#[UsesClass(Reference::class)]
#[UsesClass(\SqlSemantics\Core\Language::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Analyzer::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Vocabulary::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\TriviaReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\SourceComments::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Resolver::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\NameSites::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\NameSite::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Forms::class)]
#[UsesClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Core\Ast\Identifiers::class)]
#[UsesClass(\SqlSemantics\Core\Ast\SchemaReader::class)]
#[UsesClass(\SqlSemantics\Core\Ast\ColumnReader::class)]
#[UsesClass(\SqlSemantics\Core\Ast\ColumnProperties::class)]
#[UsesClass(\SqlSemantics\Core\Ast\ConstraintReader::class)]
#[UsesClass(\SqlSemantics\Core\Ast\TypeReader::class)]
#[UsesClass(\SqlSemantics\Core\Ast\TokenGroups::class)]
#[UsesClass(\SqlSemantics\Core\Ast\Tree::class)]
#[UsesClass(\SqlSemantics\Core\Policy\RelationRules::class)]
#[UsesClass(\SqlSemantics\Core\Policy\SyntaxRules::class)]
#[UsesClass(\SqlSemantics\Statement\Statement::class)]
#[UsesClass(\SqlSemantics\Statement\Resolution::class)]
#[UsesClass(\SqlSemantics\Statement\Comments::class)]
#[UsesClass(\SqlSemantics\Statement\Writer::class)]
#[UsesClass(\SqlSemantics\Statement\Traversal::class)]
#[UsesClass(\SqlSemantics\Statement\Assertion::class)]
#[UsesClass(\SqlSemantics\Statement\ImmutableGraph::class)]
#[UsesClass(\SqlSemantics\Statement\Declaration\TableDefinition::class)]
#[UsesClass(\SqlSemantics\Statement\Declaration\ColumnDefinition::class)]
#[UsesClass(\SqlSemantics\Statement\Declaration\TypeDescriptor::class)]
#[UsesClass(\SqlSemantics\Statement\Declaration\Invariant::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\NameRules::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\SchemaRules::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\TypeRules::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\Builtin::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\TypeName::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\TypeDeclaration::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Numbers::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\TypeReader::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\NameRules::class)]
#[Medium]
final class RelationsTest extends TestCase
{
    public function testApplyPutsDeclarationsInForceAndTakesDropsOut(): void
    {
        $relations = new Relations(MySqlDialect::MySql->platform()->names(), 'app');
        $users = (new Semantics(MySqlDialect::MySql))->analyze('CREATE TABLE users (id INT)', []);
        $table = Resolved::of($users)->declarations[0];
        $relations->apply(new Reference(new Name('users'), ['users'], ReferenceKind::Declaration, null, $table), $users);
        self::assertSame([$table, $users], $relations->lookup('app', 'users'));
        $relations->apply(new Reference(new Name('users'), ['users'], ReferenceKind::Dependency, $users, $table), $users);
        self::assertSame([$table, $users], $relations->lookup('app', 'users'));
        $relations->apply(new Reference(new Name('users'), ['app', 'users'], ReferenceKind::Drop, $users, $table), $users);
        self::assertNull($relations->lookup('app', 'users'));
    }

    public function testDeclareAndLookupKeepTheTableAndItsOwner(): void
    {
        $relations = new Relations(MySqlDialect::MySql->platform()->names(), '');
        $relations->declare('app', 'users', null, null);
        self::assertSame([null, null], $relations->lookup('app', 'users'));
        self::assertNull($relations->lookup('other', 'users'));
    }

    public function testDropRemovesOnlyTheNamedTable(): void
    {
        $relations = new Relations(MySqlDialect::MySql->platform()->names(), '');
        $relations->declare('app', 'users', null, null);
        $relations->declare('app', 'orders', null, null);
        $relations->drop('app', 'users');
        self::assertNull($relations->lookup('app', 'users'));
        self::assertNotNull($relations->lookup('app', 'orders'));
    }

    public function testLookupAnswersNothingWhenNoTableIsInForce(): void
    {
        self::assertNull((new Relations(MySqlDialect::MySql->platform()->names(), ''))->lookup('app', 'users'));
    }

    public function testQualifiedUsesTheDefaultSchemaAndKeepsTheLastTwoParts(): void
    {
        $relations = new Relations(MySqlDialect::MySql->platform()->names(), 'app');
        self::assertSame(['app', 'users'], $relations->qualified(['users']));
        self::assertSame(['other', 'users'], $relations->qualified(['other', 'users']));
        self::assertSame(['other', 'users'], $relations->qualified(['catalog', 'other', 'users']));
    }

    public function testSameComparesUnderTheRelationNamePolicy(): void
    {
        $relations = new Relations(PostgreSqlDialect::PostgreSql->platform()->names(), 'public');
        self::assertTrue($relations->same('public', 'users', 'public', 'users'));
        self::assertFalse($relations->same('public', 'users', 'other', 'users'));
        self::assertFalse($relations->same('public', 'users', 'public', 'orders'));
    }

    public function testDefineRecordsSinglePartNamesOnly(): void
    {
        $relations = new Relations(MySqlDialect::MySql->platform()->names(), '');
        $relations->define(['recent']);
        $relations->define(['app', 'ignored']);
        self::assertTrue($relations->isCommon(['recent']));
        self::assertFalse($relations->isCommon(['ignored']));
    }

    public function testIsCommonComparesUnderTheNamePolicyAndRejectsQualifiedNames(): void
    {
        $relations = new Relations(MySqlDialect::MySql->platform()->names(), '');
        $relations->define(['recent']);
        self::assertTrue($relations->isCommon(['RECENT']));
        self::assertFalse($relations->isCommon(['app', 'recent']));
        self::assertFalse($relations->isCommon(['other']));
    }
}
