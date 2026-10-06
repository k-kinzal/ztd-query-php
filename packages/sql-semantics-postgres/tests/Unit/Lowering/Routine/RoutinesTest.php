<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Lowering\Routine\Routines;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\ChangeOwner;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Comment;
use SqlSemantics\Platform\PostgreSql\Statement\Object\CopyCollation;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Drop;
use SqlSemantics\Platform\PostgreSql\Statement\Object\ExtensionDependency;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Rename;
use SqlSemantics\Platform\PostgreSql\Statement\Object\SecurityLabel;
use SqlSemantics\Platform\PostgreSql\Statement\Object\SetSchema;
use SqlSemantics\Platform\PostgreSql\Statement\Operator\AlterOperator;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\AlterRoutine;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\CreateFunction;

#[CoversClass(Routines::class)]
#[Small]
final class RoutinesTest extends TestCase
{
    public function testStatementDispatchesEveryStatementOfTheFamily(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('COMMENT ON TABLE t IS NULL; ALTER FUNCTION f() STABLE; SECURITY LABEL ON SCHEMA s IS NULL; ALTER SCHEMA s OWNER TO r; ALTER TYPE t SET SCHEMA s; ALTER INDEX i DEPENDS ON EXTENSION e; CREATE FUNCTION f() RETURNS int4 RETURN 1; DROP AGGREGATE a(*); ALTER SCHEMA s RENAME TO t; CREATE COLLATION c FROM d; ALTER OPERATOR = (int4, int4) SET (hashes)');
        $routines = $lowering->routines;
        self::assertSame(
            [Comment::class, AlterRoutine::class, SecurityLabel::class, ChangeOwner::class, SetSchema::class, ExtensionDependency::class, CreateFunction::class, Drop::class, Rename::class, CopyCollation::class, AlterOperator::class],
            [
                $routines->statement($tree->find('CommentStmt')[0])::class,
                $routines->statement($tree->find('AlterFunctionStmt')[0])::class,
                $routines->statement($tree->find('SecLabelStmt')[0])::class,
                $routines->statement($tree->find('AlterOwnerStmt')[0])::class,
                $routines->statement($tree->find('AlterObjectSchemaStmt')[0])::class,
                $routines->statement($tree->find('AlterObjectDependsStmt')[0])::class,
                $routines->statement($tree->find('CreateFunctionStmt')[0])::class,
                $routines->statement($tree->find('RemoveAggrStmt')[0])::class,
                $routines->statement($tree->find('RenameStmt')[0])::class,
                $routines->statement($tree->find('DefineStmt')[0])::class,
                $routines->statement($tree->find('AlterOperatorStmt')[0])::class,
            ],
        );
    }

    public function testStatementRejectsAStatementOfAnotherFamily(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CHECKPOINT');
        $this->expectExceptionMessage('No semantic rule is implemented for: CheckPointStmt: CHECKPOINT');
        $lowering->routines->statement($tree->find('CheckPointStmt')[0]);
    }

    public function testFunctionSignatureLowersAName(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP FUNCTION f(int), g');
        self::assertNull($lowering->routines->functionSignature($tree->find('function_with_argtypes')[1])->arguments);
    }

    public function testFunctionSignaturesLowersTheList(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP FUNCTION f(int), g');
        self::assertCount(2, $lowering->routines->functionSignatures($tree->find('function_with_argtypes_list')[0]));
    }

    public function testAggregateSignatureLowersTheArguments(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP AGGREGATE a(int), b(*)');
        self::assertCount(1, $lowering->routines->aggregateSignature($tree->find('aggregate_with_argtypes')[0])->arguments->direct);
    }

    public function testAggregateSignaturesLowersTheList(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP AGGREGATE a(int), b(*)');
        self::assertCount(2, $lowering->routines->aggregateSignatures($tree->find('aggregate_with_argtypes_list')[0]));
    }

    public function testOperatorSignatureLowersTheOperator(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP OPERATOR s.+ (int, int)');
        self::assertSame(['s', '+'], [$lowering->routines->operatorSignature($tree->find('operator_with_argtypes')[0])->operator->qualifiers[0]->value, $lowering->routines->operatorSignature($tree->find('operator_with_argtypes')[0])->operator->name->value]);
    }

    public function testOperatorSignaturesLowersTheList(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP OPERATOR + (int, int), - (NONE, int)');
        self::assertCount(2, $lowering->routines->operatorSignatures($tree->find('operator_with_argtypes_list')[0]));
    }

    public function testObjectKindLowersTheKind(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP FOREIGN DATA WRAPPER w');
        self::assertSame(ObjectKind::ForeignDataWrapper, $lowering->routines->objectKind($tree->find('drop_type_name')[0]));
    }

    public function testAttributeChangesReadsTheAttributesOfTheCommand(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('ALTER TYPE t SET (storage = plain, analyse = f)');
        $changes = $lowering->routines->attributeChanges($tree->find('operator_def_list')[0], \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\TypeChangeAttribute::class);
        self::assertSame([\SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\TypeChangeAttribute::Storage, null], [$changes[0]->attribute->known, $changes[1]->attribute->known]);
    }
}
