<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Access;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Access\RelationEntry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(RelationEntry::class)]
#[Medium]
final class RelationEntryTest extends TestCase
{
    public function testDeclaredRequiresAProgramCountDeclaration(): void
    {
        $variable = new \SqlSemantics\Platform\MySql\Statement\Call\Window\RoutineVariable(new \SqlSemantics\Statement\Identifier\Name('n'));
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1327);
        (new RelationEntry())->declared($variable, (new Instance())->connect());
    }

    public function testMaintenanceRequiresTheDatabaseOfAnUnqualifiedTarget(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $operation = $session->semantics()->analyze('CHECK TABLE t, pbt.later', []);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $operation);
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);
        (new RelationEntry())->maintenance($operation, $operation->statementInformation(), '', $session);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerInputs(): iterable
    {
        yield 'maintenance database before a later table' => ['ANALYZE TABLE t, pbt.later', 1046];
        yield 'active common table' => ['WITH c AS (SELECT 1 FROM t QUALIFY 1) SELECT * FROM c', 1046];
        yield 'count before relation' => ['SELECT NTILE(n) OVER () + (SELECT 1 FROM absent)', 1327];
        yield 'relation before count' => ['SELECT (SELECT 1 FROM absent) + NTILE(n) OVER ()', 1046];
    }

    public function testCountsLeavesAnUnusedCommonTableDefinitionInactive(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $operation = $session->semantics()->analyze('WITH unused AS (SELECT NTILE(n) OVER ()) SELECT 1', []);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $operation);
        (new RelationEntry())->counts($operation, $operation->statementInformation(), $session);
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testNodesFollowsOnlyTheResolvedCommonTableDefinitions(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $operation = $session->semantics()->analyze('WITH unused AS (SELECT * FROM ignored), active AS (SELECT * FROM reached) SELECT * FROM active', []);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $operation);
        $nodes = (new RelationEntry())->nodes($operation, $operation->statementInformation());
        $tables = array_filter($nodes, static fn ($node): bool => $node instanceof \SqlSemantics\Platform\MySql\Statement\Relation\TableReference);
        self::assertSame(['active', 'reached'], array_values(array_map(static fn ($table): string => $table->name->name->value, $tables)));
    }

    #[DataProvider('providerInputs')]
    public function testCheckOrdersTheRetainedNamesBeforeTheLaterCondition(string $sql, int $code): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $operation = $session->semantics()->analyze($sql, $session->semantics()->context([], true));
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $operation);
        $session->text = 'unrelated invalid input';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode($code);
        (new RelationEntry())->check($operation, $operation->statementInformation(), $session);
    }

    public function testCountsResolvesAnExplicitTableLimitFromItsConstructedStructure(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $session->text = 'opaque request label';
        $query = new \SqlSemantics\Platform\MySql\Statement\Query\QueryExpression(null, new \SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'), new \SqlSemantics\Statement\Identifier\Name('d'))), limit: new \SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit(new \SqlSemantics\Platform\MySql\Statement\Query\Clause\ProgramVariable(new \SqlSemantics\Statement\Identifier\Name('n'))));
        $derivation = new \SqlSemantics\Construction\Derivation($session->semantics()->context([], true));
        $derivation->query($query, $derivation->environment());
        $this->expectExceptionCode(1327);
        $this->expectExceptionMessage('Undeclared variable: n');
        (new RelationEntry())->counts($query, (new \SqlSemantics\Platform\MySql\Construction\StatementResults())->of($derivation), $session);
    }

    public function testAliasesRetainsTheLaterRelationIdentity(): void
    {
        $name = new \SqlSemantics\Statement\Identifier\Name('t');
        $first = new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new \SqlSemantics\Statement\Identifier\QualifiedName($name));
        $later = new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new \SqlSemantics\Statement\Identifier\QualifiedName($name));
        $problem = new \SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable($name, $later);
        $facts = new \SqlSemantics\Platform\MySql\Statement\StatementSemantics(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847), output: null, diagnostics: [$problem]);
        $aliases = (new RelationEntry())->aliases($facts);
        self::assertArrayNotHasKey(spl_object_id($first), $aliases);
        self::assertSame($problem, $aliases[spl_object_id($later)]);
    }

    public function testImplicitDistinguishesACteFromAnUnboundBaseTable(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $operation = $session->semantics()->analyze('WITH c AS (SELECT 1) SELECT * FROM c,t', []);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $operation);
        $tables = (new \MySqlMemory\Evaluation\Compile\Walker())->find($operation, \SqlSemantics\Platform\MySql\Statement\Relation\TableReference::class);
        self::assertFalse((new RelationEntry())->implicit($tables[0], $operation->statementInformation()));
        self::assertTrue((new RelationEntry())->implicit($tables[1], $operation->statementInformation()));
    }

    public function testVariablesKeepsReadDependenciesSeparateFromAssignmentTargets(): void
    {
        $read = new \SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable('missing');
        $assigned = new \SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable('assigned', true);
        $structured = new \SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable('structured', false, true);
        $facts = new \SqlSemantics\Platform\MySql\Statement\StatementSemantics(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847), output: null, diagnostics: [$read, $assigned, $structured]);
        self::assertSame(['missing' => $read], (new RelationEntry())->variables($facts));
    }
}
