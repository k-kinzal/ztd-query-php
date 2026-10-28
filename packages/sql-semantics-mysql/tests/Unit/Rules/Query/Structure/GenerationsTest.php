<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Structure;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Statement\Account\Password;
use SqlSemantics\Platform\MySql\Statement\Account\PasswordFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Json\JsonValueCall;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightLevel;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightString;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\ProcedureAnalyse;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator;
use SqlSemantics\Platform\MySql\Statement\Query\ValueRow;
use SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Platform\MySql\Statement\Query\With\With;
use SqlSemantics\Platform\MySql\Statement\Relation\Join\JoinOperator;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\PasswordAssignment;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\UserAssignment;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;

#[CoversClass(Generations::class)]
#[Small]
final class GenerationsTest extends TestCase
{
    public function testCheckRejectsAnExpressionDefaultWithLegacyAlterIgnore(): void
    {
        $default = new \SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultExpression(new NumberLiteral('1'));
        $command = new \SqlSemantics\Platform\MySql\Statement\Alter\Column\DefaultSetting(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnName(new Name('a')), $default);
        $this->expectExceptionMessage('A query cannot combine features removed before MySQL 8.0 with features introduced in MySQL 8.0 or later.');

        new \SqlSemantics\Platform\MySql\Statement\Alter\AlterTable(new QualifiedName(new Name('t')), [$command], true);
    }

    public function testCheckRejectsPersistResetWithALegacyDefaultCollationParameter(): void
    {
        $parameter = new \SqlSemantics\Platform\MySql\Statement\Routine\Parameter(new Name('x'), new \SqlSemantics\Platform\MySql\Statement\Type\Decimal(), new \SqlSemantics\Platform\MySql\Statement\Name\CollationName(null));
        $body = new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetPersist();
        $this->expectExceptionMessage('A query cannot combine features removed before MySQL 8.0 with features introduced in MySQL 8.0 or later.');

        new \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure(new QualifiedName(new Name('p')), new \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList([$parameter]), $body);
    }

    public function testTransitionDistinguishesTheLegacyMinorReleaseBoundary(): void
    {
        $rules = new Generations();
        $alter = new \SqlSemantics\Platform\MySql\Statement\Alter\AlterTable(new QualifiedName(new Name('t')), [], true);

        self::assertSame(1, $rules->transition($alter));
        self::assertSame(1, $rules->transition(new \SqlSemantics\Platform\MySql\Statement\Account\ExpireUserPasswords([new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser()])));
        self::assertSame(1, $rules->transition(new PasswordAssignment(new Password(new Text('pw')))));
        self::assertSame(2, $rules->transition(new \SqlSemantics\Platform\MySql\Statement\Alter\Modifier\ValidationOption(true)));
        self::assertSame(0, $rules->transition(new NumberLiteral('1')));
        self::assertSame(0, $rules->transition(new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new Name(str_repeat('u', 16)))));
        self::assertSame(0, $rules->transition(new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new Name(str_repeat('u', 17)))));
    }

    public function testCheckRejectsIgnoreWithValidation(): void
    {
        $this->expectExceptionMessage('A structure cannot combine MySQL 5.6-only forms with forms introduced in MySQL 5.7 or later.');

        $column = new \SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnName(new Name('a')), new \SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn(new \SqlSemantics\Platform\MySql\Statement\Type\Integral(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int)));
        new \SqlSemantics\Platform\MySql\Statement\Alter\AlterTable(new QualifiedName(new Name('t')), [new \SqlSemantics\Platform\MySql\Statement\Alter\Modifier\ValidationOption(true), new \SqlSemantics\Platform\MySql\Statement\Alter\Column\AddColumn($column)], true);
    }

    public function testCheckRejectsIndexVisibilityWithRemovedPartitionUpgrade(): void
    {
        $this->expectExceptionMessage('A query cannot combine features removed before MySQL 8.0 with features introduced in MySQL 8.0 or later.');

        new \SqlSemantics\Platform\MySql\Statement\Alter\AlterTable(new QualifiedName(new Name('t')), [new \SqlSemantics\Platform\MySql\Statement\Alter\Command\IndexVisibility(new Name('i'), true), new \SqlSemantics\Platform\MySql\Statement\Alter\Partition\UpgradePartitioning()]);
    }

    /**
     * @return iterable<string, array{object, int}>
     */
    public static function providerRequirements(): iterable
    {
        yield 'secondary engine' => [new \SqlSemantics\Platform\MySql\Statement\Table\Option\SecondaryEngineOption(null), 2];
        yield 'system assignment keyword' => [\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetWord::System, 2];
        yield 'row assignment keyword' => [\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetWord::Row, 2];
        yield 'password assignment list item' => [new PasswordAssignment(new Password(new Text('pw'))), 1];
        yield 'functional index' => [new \SqlSemantics\Platform\MySql\Statement\Table\Key\ExpressionPart(new NumberLiteral('1')), 2];
        yield 'dynamic privilege' => [new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\DynamicPrivilege(new Name('SYSTEM_USER')), 2];
        yield 'external routine body' => [new \SqlSemantics\Platform\MySql\Statement\Routine\ExternalBody(new Text('body')), 2];
        yield 'random password' => [\SqlSemantics\Platform\MySql\Statement\Account\User\Credential::RandomPassword, 2];
        yield 'default parameter collation' => [new \SqlSemantics\Platform\MySql\Statement\Routine\Parameter(new Name('arg'), new \SqlSemantics\Platform\MySql\Statement\Type\Character(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind::Char), new \SqlSemantics\Platform\MySql\Statement\Name\CollationName(null)), 1];
        yield 'repeated temporary keyword' => [new \SqlSemantics\Platform\MySql\Statement\Table\CreateTable(new QualifiedName(new Name('t')), [new \SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnName(new Name('a')), new \SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn(new \SqlSemantics\Platform\MySql\Statement\Type\Integral(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int)))], temporaryWords: 2), 1];
        yield 'ordinary literal' => [new NumberLiteral('1'), 0];
        yield 'ordinary union' => [SetOperator::Union, 0];
        yield 'procedure' => [new ProcedureAnalyse(), 1];
        yield 'cache' => [SelectOption::Cache, 1];
        yield 'window' => [new WindowSpec(), 2];
        yield 'intersect' => [SetOperator::Intersect, 2];
        yield 'except' => [SetOperator::Except, 2];
        yield 'leading column dot' => [new ColumnUse(new Name('a'), new QualifiedName(new Name('t')), \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written), 1];
        yield 'leading table dot' => [new TableReference(new QualifiedName(new Name('t')), dot: \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written), 1];
        yield 'legacy explain modifier' => [\SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainModifier::Partitions, 1];
        yield 'query cache reset' => [new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetQueryCache(), 1];
        yield 'binary logs reset' => [new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetBinaryLogs(\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current), 2];
        yield 'replica reset' => [new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetReplica(\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current), 2];
    }

    #[DataProvider('providerRequirements')]
    public function testRequirementDescribesTheFeatureGeneration(object $value, int $expected): void
    {
        self::assertSame($expected, (new Generations())->requirement($value));
    }

    /**
     * @return iterable<string, array{Closure(): Node, 1?: string}>
     */
    public static function providerConflicts(): iterable
    {
        yield 'upgrade partitioning with check enforcement' => [static fn (): Node => new \SqlSemantics\Platform\MySql\Statement\Alter\AlterTable(new QualifiedName(new Name('t')), [new \SqlSemantics\Platform\MySql\Statement\Alter\Partition\UpgradePartitioning(), new \SqlSemantics\Platform\MySql\Statement\Alter\Command\ConstraintEnforcement(\SqlSemantics\Platform\MySql\Statement\Alter\Command\ElementKind::Check, new Name('c'), false)])];

        yield 'resource group in cache query' => [static fn (): Node => new Select([SelectOption::Cache], [new SelectExpression(new NumberLiteral('1'))], hints: [new \SqlSemantics\Platform\MySql\Statement\Hint\Form\ResourceGroupHint('batch')])];
        yield 'direction in qualify grouping' => [static fn (): Node => new Select([], [new SelectExpression(new NumberLiteral('1'))], groupBy: new \SqlSemantics\Platform\MySql\Statement\Query\Clause\Grouping([new \SqlSemantics\Platform\MySql\Statement\Query\OrderItem(new NumberLiteral('2.5'), \SqlSemantics\Platform\MySql\Statement\Query\Direction::Descending)]), qualify: new NumberLiteral('3'))];
        yield 'legacy hash in additional factor' => [static fn (): Node => new \SqlSemantics\Platform\MySql\Statement\Account\CreateUser(false, [new \SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification(new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new Name('u')), new \SqlSemantics\Platform\MySql\Statement\Account\User\Identification(null, \SqlSemantics\Platform\MySql\Statement\Account\User\Credential::Password, new Text('pw')), [new \SqlSemantics\Platform\MySql\Statement\Account\User\Identification(null, \SqlSemantics\Platform\MySql\Statement\Account\User\Credential::PasswordHash, new Text('hash'))])])];
        yield 'dynamic privilege with repeated grant option' => [static fn (): Node => new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges([new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\DynamicPrivilege(new Name('SYSTEM_USER'))], \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Table, new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel(), [new \SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification(new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new Name('u')))], options: [new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantOptionRight(), new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantOptionRight()])];
        yield 'repeated temporary with enforced constraint' => [static fn (): Node => new \SqlSemantics\Platform\MySql\Statement\Table\CreateTable(new QualifiedName(new Name('t')), [new \SqlSemantics\Platform\MySql\Statement\Table\Key\CheckConstraint(new NumberLiteral('1'), enforced: true)], temporaryWords: 2), 'A query cannot combine features removed before MySQL 8.0 with features introduced in MySQL 8.0 or later.'];
        yield 'deferred repeated temporary with enforced constraint' => [static fn (): Node => new \SqlSemantics\Platform\MySql\Statement\Table\CreateTable(new QualifiedName(new Name('t')), [new \SqlSemantics\Platform\MySql\Statement\Table\Key\CheckConstraint(new NumberLiteral('1'), enforced: true)], temporaryWords: 2)];
        yield 'default charset with transaction' => [static fn (): Node => new \SqlSemantics\Platform\MySql\Statement\Table\CreateTable(new QualifiedName(new Name('t')), options: [new \SqlSemantics\Platform\MySql\Statement\Table\Option\CharsetOption(new \SqlSemantics\Platform\MySql\Statement\Name\CharsetName(null)), new \SqlSemantics\Platform\MySql\Statement\Table\Option\StartTransaction()]), 'A query cannot combine features removed before MySQL 8.0 with features introduced in MySQL 8.0 or later.'];
        yield 'deferred default charset with transaction' => [static fn (): Node => new \SqlSemantics\Platform\MySql\Statement\Table\CreateTable(new QualifiedName(new Name('t')), options: [new \SqlSemantics\Platform\MySql\Statement\Table\Option\CharsetOption(new \SqlSemantics\Platform\MySql\Statement\Name\CharsetName(null)), new \SqlSemantics\Platform\MySql\Statement\Table\Option\StartTransaction()])];
        yield 'repeated temporary with secondary engine' => [static fn (): Node => new \SqlSemantics\Platform\MySql\Statement\Table\CreateTable(new QualifiedName(new Name('t')), options: [new \SqlSemantics\Platform\MySql\Statement\Table\Option\SecondaryEngineOption(null)], temporaryWords: 2), 'A query cannot combine features removed before MySQL 8.0 with features introduced in MySQL 8.0 or later.'];
        yield 'deferred repeated temporary with secondary engine' => [static fn (): Node => new \SqlSemantics\Platform\MySql\Statement\Table\CreateTable(new QualifiedName(new Name('t')), options: [new \SqlSemantics\Platform\MySql\Statement\Table\Option\SecondaryEngineOption(null)], temporaryWords: 2)];
        yield 'password list item with system keyword' => [static fn (): Node => new SetVariables([new PasswordAssignment(new Password(new Text('pw'))), new \SqlSemantics\Platform\MySql\Statement\Utility\Set\SystemAssignment(new \SqlSemantics\Platform\MySql\Statement\Variable\SystemTarget(new Name('time_zone')), \SqlSemantics\Platform\MySql\Statement\Utility\Set\SetWord::System)])];
        yield 'cache with repeated locks' => [static fn (): Node => new Select([SelectOption::Cache], [new SelectExpression(new NumberLiteral('1'))], locking: [new \SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause(\SqlSemantics\Platform\MySql\Statement\Query\Locking\LockStrength::Update), new \SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause(\SqlSemantics\Platform\MySql\Statement\Query\Locking\LockStrength::Update)])];
        yield 'legacy grant options with grant as' => [static fn (): Node => new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges([new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges()], \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Table, new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel(), [new \SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification(new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new Name('user')))], new \SqlSemantics\Platform\MySql\Statement\Account\Option\TlsRequirement(\SqlSemantics\Platform\MySql\Statement\Account\Option\TlsKind::X509), as: new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantAs(new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new Name('grantor'))))];
        yield 'weight levels in a modern row' => [static fn (): Node => new ValuesQuery([new ValueRow([new WeightString(new NumberLiteral('1'), levels: [new WeightLevel(new Numeral('1'))])])])];
        yield 'query cache with binary logs reset' => [static fn (): Node => new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\Reset([new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetBinaryLogs(\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current), new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetQueryCache()])];
        yield 'query cache with replica reset' => [static fn (): Node => new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\Reset([new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetReplica(\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current), new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetQueryCache()])];
        yield 'legacy slave with binary logs reset' => [static fn (): Node => new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\Reset([new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetBinaryLogs(\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current), new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetReplica(\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Legacy)])];
        yield 'default database collation with read only' => [static fn (): Node => new \SqlSemantics\Platform\MySql\Statement\Server\Database\AlterDatabase(null, [new \SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCollation(new \SqlSemantics\Platform\MySql\Statement\Name\CollationName(null)), new \SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseReadOnly(null)])];
        yield 'legacy password and modern assignment' => [static fn (): Node => new SetVariables([new PasswordAssignment(new Password(new Text('pw'), PasswordFunction::Password)), new UserAssignment(new UserVariable(new Name('x')), new JsonValueCall(new StringLiteral(['{}']), new StringLiteral(['$.x'])))])];
        yield 'with and procedure' => [static fn (): Node => new QueryExpression(
            new With(false, [new CommonTableExpression(new Name('c'), [], new Select([], [new SelectExpression(new NumberLiteral('1'))]))]),
            new Select([], [new SelectExpression(new NumberLiteral('2'))], from: new \SqlSemantics\Platform\MySql\Statement\Relation\Dual(), procedure: new ProcedureAnalyse()),
        )];
        yield 'procedure in common table' => [static fn (): Node => new CommonTableExpression(new Name('c'), [], new Select([], [new SelectExpression(new NumberLiteral('1'))], from: new \SqlSemantics\Platform\MySql\Statement\Relation\Dual(), procedure: new ProcedureAnalyse()))];
        yield 'modern query in legacy projection' => [static fn (): Node => new Select([SelectOption::Cache], [new SelectExpression(new ScalarSubquery(new ValuesQuery([new ValueRow([new NumberLiteral('1')])])))])];
        yield 'legacy query in modern rows' => [static fn (): Node => new ValuesQuery([new ValueRow([new ScalarSubquery(new Select([], [new SelectExpression(new WeightString(new NumberLiteral('1'), levels: [new WeightLevel(new Numeral('1'))]))]))])])];
        yield 'qualify and procedure' => [static fn (): Node => new Select([], [new SelectExpression(new NumberLiteral('1'))], qualify: new NumberLiteral('1'), from: new \SqlSemantics\Platform\MySql\Statement\Relation\Dual(), procedure: new ProcedureAnalyse())];
        yield 'except and unparenthesized limit' => [static fn (): Node => new \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation(new Select([], [new SelectExpression(new NumberLiteral('1'))], limit: new \SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit(new NumberLiteral('1'))), SetOperator::Except, null, new Select([], [new SelectExpression(new NumberLiteral('2'))]))];
        yield 'modern value with leading column dot' => [static fn (): Node => new ValuesQuery([new ValueRow([new ColumnUse(new Name('a'), new QualifiedName(new Name('t')), \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written)])])];
        yield 'legacy explain modifier and modern query' => [static fn (): Node => new \SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain(new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new ValuesQuery([new ValueRow([new NumberLiteral('1')])])), modifier: \SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainModifier::Partitions)];
        yield 'modern with and dotted table' => [static fn (): Node => new QueryExpression(new With(false, [new CommonTableExpression(new Name('c'), [], new Select([], [new SelectExpression(new NumberLiteral('1'))]))]), new Select([], [new SelectExpression(new NumberLiteral('1'))], from: new TableReference(new QualifiedName(new Name('t')), dot: \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written)))];
        yield 'disjoint sibling subqueries in a scalar root' => [static fn (): Node => new Comparison(
            ComparisonOperator::Equal,
            new ScalarSubquery(new Select([], [new SelectExpression(new WeightString(new NumberLiteral('1'), levels: [new WeightLevel(new Numeral('1'))]))])),
            new ScalarSubquery(new ValuesQuery([new ValueRow([new NumberLiteral('1')])])),
        )];
    }

    /**
     * @param Closure(): Node $construct
     */
    #[DataProvider('providerConflicts')]
    public function testCheckRejectsCrossGenerationGraphsDuringConstruction(Closure $construct, string $message = 'A query cannot combine features removed before MySQL 8.0 with features introduced in MySQL 8.0 or later.'): void
    {
        $this->expectExceptionMessage($message);

        $construct();
    }

    public function testCheckAllowsAnEntirelyLegacyGraph(): void
    {
        $query = new Select([SelectOption::Cache], [new SelectExpression(new NumberLiteral('1'))], from: new \SqlSemantics\Platform\MySql\Statement\Relation\Dual(), procedure: new ProcedureAnalyse());
        (new Generations())->check($query);

        self::assertSame(SelectOption::Cache, $query->options[0]);
    }

    public function testCheckAllowsAnEntirelyModernGraph(): void
    {
        $body = new ValuesQuery([new ValueRow([new NumberLiteral('1')])]);
        $query = new QueryExpression(new With(false, [new CommonTableExpression(new Name('c'), [], new Select([], [new SelectExpression(new NumberLiteral('2'))]))]), $body);
        (new Generations())->check($query);

        self::assertSame($body, $query->body);
    }
    public function testCheckRefusesConflictingSpellingProfilesAcrossItems(): void
    {
        $expression = new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new NumberLiteral('2'));
        $layout = new \SqlSemantics\Statement\Spelling\Layout([new \SqlSemantics\Statement\Spelling\Spelled('', '1'), new \SqlSemantics\Statement\Spelling\Spelled('', '+'), new \SqlSemantics\Statement\Spelling\Spelled('', '2')]);
        $ordinary = new SelectExpression($expression, layout: $layout);
        $ansi = new SelectExpression(new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new NumberLiteral('2')), layout: $layout, spellingProfile: new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847, new \SqlSemantics\Contract\LexicalSettings('ANSI_QUOTES')));

        $this->expectExceptionMessage('one compatible language profile');

        new Select([], [$ordinary, $ansi]);
    }

    public function testCheckRefusesAnOperatorConflictingWithAnotherItemsSpelling(): void
    {
        $expression = new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new NumberLiteral('2'));
        $layout = new \SqlSemantics\Statement\Spelling\Layout([new \SqlSemantics\Statement\Spelling\Spelled('', '1'), new \SqlSemantics\Statement\Spelling\Spelled('', '+'), new \SqlSemantics\Statement\Spelling\Spelled('', '2')]);
        $ordinary = new SelectExpression($expression, layout: $layout);
        $concat = new SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Concatenation(new StringLiteral(['a']), new StringLiteral(['b'])));

        $this->expectExceptionMessage('both enabled and disabled PIPES_AS_CONCAT');

        new Select([], [$ordinary, $concat]);
    }

    /**
     * @return iterable<string, array{Closure(): Node}>
     */
    public static function providerSharedOccurrences(): iterable
    {
        yield 'arithmetic literal' => [static function (): Node {
            $literal = new NumberLiteral('1');

            return new Arithmetic(ArithmeticOperator::Plus, $literal, $literal);
        }];
        yield 'parameter' => [static function (): Node {
            $parameter = new Parameter('?');

            return new Arithmetic(ArithmeticOperator::Plus, $parameter, $parameter);
        }];
        yield 'separate wrappers' => [static function (): Node {
            $literal = new NumberLiteral('1');

            return new Arithmetic(ArithmeticOperator::Plus, new Grouped($literal), new Grouped($literal));
        }];
        yield 'select item' => [static function (): Node {
            $item = new SelectExpression(new NumberLiteral('1'));

            return new Select([], [$item, $item]);
        }];
        yield 'subquery' => [static function (): Node {
            $query = new Select([], [new SelectExpression(new NumberLiteral('1'))]);

            return new Select([], [new SelectExpression(new ScalarSubquery($query)), new SelectExpression(new ScalarSubquery($query))]);
        }];
        yield 'table reference' => [static function (): Node {
            $table = new TableReference(new QualifiedName(new Name('t')));

            return new Select([], [new SelectExpression(new NumberLiteral('1'))], from: new JoinedTable($table, JoinOperator::Join, $table));
        }];
    }

    /**
     * @param Closure(): Node $construct The public constructor composition
     */
    #[DataProvider('providerSharedOccurrences')]
    public function testCheckRejectsSharedOccurrencesBeforeDerivation(Closure $construct): void
    {
        $this->expectExceptionMessage('A statement node occurs at one position only.');

        $construct();
    }

    public function testCheckAllowsSharedNamesAndEnumValues(): void
    {
        $name = new Name('a');
        $query = new Select([SelectOption::All, SelectOption::All], [new SelectExpression(new ColumnUse($name), $name), new SelectExpression(new ColumnUse($name), $name)]);

        (new Generations())->check($query);

        self::assertInstanceOf(SelectExpression::class, $query->items[0]);
        self::assertInstanceOf(SelectExpression::class, $query->items[1]);
        self::assertSame($name, $query->items[0]->alias);
        self::assertSame($name, $query->items[1]->alias);
    }

    public function testCheckAllowsOneOccurrenceToBeUsedInIndependentStatements(): void
    {
        $literal = new NumberLiteral('1');
        $first = new Select([], [new SelectExpression($literal)]);
        $second = new Select([], [new SelectExpression($literal)]);

        self::assertInstanceOf(SelectExpression::class, $first->items[0]);
        self::assertInstanceOf(SelectExpression::class, $second->items[0]);
        self::assertSame($first->items[0]->expression, $second->items[0]->expression);
    }
    public function testCheckRejectsNamedParametersUnderNativeObservableSpellings(): void
    {
        $layout = new \SqlSemantics\Statement\Spelling\Layout([new \SqlSemantics\Statement\Spelling\Spelled('', '1'), new \SqlSemantics\Statement\Spelling\Spelled('', '+'), new \SqlSemantics\Statement\Spelling\Spelled('', '2')]);
        $item = new SelectExpression(new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new NumberLiteral('2')), layout: $layout);

        $this->expectExceptionMessage('A named parameter cannot accompany a spelling that requires native parameters.');

        new Select([], [$item, new SelectExpression(new Parameter(':a'))]);
    }
    public function testCheckRejectsConflictingRequirementsOnOneRoutine(): void
    {
        $this->expectExceptionMessage('A query cannot combine features removed before MySQL 8.0 with features introduced in MySQL 8.0 or later.');
        new \SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction(new QualifiedName(new Name('f')), new \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList([]), new \SqlSemantics\Platform\MySql\Statement\Type\Decimal(), new \SqlSemantics\Platform\MySql\Statement\Routine\Definition\FunctionImplementation(new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement(new NumberLiteral('1'))), collation: new \SqlSemantics\Platform\MySql\Statement\Name\CollationName(null), ifNotExists: true);
    }

    public function testCheckRejectsDefaultCharsetWithModernDatabaseOptions(): void
    {
        $this->expectExceptionMessage('A query cannot combine features removed before MySQL 8.0 with features introduced in MySQL 8.0 or later.');
        new \SqlSemantics\Platform\MySql\Statement\Server\Database\AlterDatabase(null, [new \SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseReadOnly(null), new \SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCharset(new \SqlSemantics\Platform\MySql\Statement\Name\CharsetName(null))]);
    }


    /**
     * @return iterable<string, array{Closure(): object}>
     */
    public static function providerDisjointStatementGenerations(): iterable
    {
        yield 'persistent variables with a password assignment' => [static fn (): object => new SetVariables([
            new \SqlSemantics\Platform\MySql\Statement\Utility\Set\NameAssignment(new Name('v'), new NumberLiteral('1'), \SqlSemantics\Platform\MySql\Statement\Variable\VariableScope::Persist),
            new PasswordAssignment(new Password(new Text('x'))),
        ])];
        yield 'persistent-only variables with a password assignment' => [static fn (): object => new SetVariables([
            new \SqlSemantics\Platform\MySql\Statement\Utility\Set\NameAssignment(new Name('v'), new NumberLiteral('1'), \SqlSemantics\Platform\MySql\Statement\Variable\VariableScope::PersistOnly),
            new PasswordAssignment(new Password(new Text('x'))),
        ])];
        yield 'spatial casts with weight levels' => [static fn (): object => new WeightString(
            new \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast(new NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Type\CastTarget(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::MultiPolygon)),
            levels: [new WeightLevel(new Numeral('1'))],
        )];
        yield 'backup locking with legacy parameter collation' => [static fn (): object => new \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure(
            new QualifiedName(new Name('p')),
            new \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList([new \SqlSemantics\Platform\MySql\Statement\Routine\Parameter(new Name('a'), new \SqlSemantics\Platform\MySql\Statement\Type\Decimal(), collation: new \SqlSemantics\Platform\MySql\Statement\Name\CollationName(null))]),
            new \SqlSemantics\Platform\MySql\Statement\Server\Lock\LockInstance(),
        )];
        yield 'column visibility with a legacy dot prefix' => [static fn (): object => new \SqlSemantics\Platform\MySql\Statement\Alter\AlterTable(
            new QualifiedName(new Name('t')),
            [new \SqlSemantics\Platform\MySql\Statement\Alter\Column\ColumnVisibility(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnName(new Name('a'), dot: \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written), false)],
        )];
        yield 'default roles with legacy password hashes' => [static fn (): object => new \SqlSemantics\Platform\MySql\Statement\Account\CreateUser(
            false,
            [new \SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification(new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new Name('u')), new \SqlSemantics\Platform\MySql\Statement\Account\User\Identification(null, \SqlSemantics\Platform\MySql\Statement\Account\User\Credential::PasswordHash, new Text('x')))],
            [new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new Name('r'))],
        )];
    }

    #[DataProvider('providerDisjointStatementGenerations')]
    public function testCheckExcludesFeaturesWithNoSharedRelease(Closure $construct): void
    {
        $this->expectExceptionMessage('A query cannot combine features removed before MySQL 8.0 with features introduced in MySQL 8.0 or later.');
        $construct();
    }
    public function testOrdinaryLeavesDeferredTypeOperandsOutsideTheStatementRole(): void
    {
        $type = new \SqlSemantics\Platform\MySql\Statement\Type\Temporal(\SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind::Time, '7');
        (new Generations())->ordinary($type);
        (new Generations())->ordinary(new NumberLiteral('1'));
        self::assertSame('7', $type->precision);
    }
}
