<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\Composition;
use Tests\Generator\Operands;

/**
 * Composes Statement values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Statement>
 */
final class StatementGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Statement> */
    use Composition;

    /**
     * @param non-empty-list<ErisGenerator<M\Statement>> $alternatives Concrete SQL forms satisfying this role
     */
    public function __construct(array $alternatives)
    {
        $this->delegate = Operands::oneOf($alternatives);
    }

    /**
     * Composes complete definitions before and after their generated target declaration exists.
     *
     * @return ErisGenerator<array{\SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, int}>
     */
    public static function definitionOptions(GrammarRelease $release): ErisGenerator
    {
        return Operands::bind(new Table\CreateTableGenerator(new \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator($release)), static function (M\Table\CreateTable $create) use ($release): ErisGenerator {
            $database = $create->name->schema ?? new Name('pbt');
            $profile = new \SqlSemantics\Contract\LanguageProfile($release);
            $missing = new \SqlSemantics\Contract\AnalysisContext($profile, [$database], [], true);
            $definition = \SqlSemantics\Facade\Semantics::fromStatement($missing, $create);
            $declared = new \SqlSemantics\Contract\AnalysisContext($profile, [$database], $definition->declarations(), true);
            return Generators::map(static fn (array $parts): array => self::definitionInput($parts, $definition, $missing, $declared), Operands::oneOf(self::definitionForms($release, $create)));
        });
    }

    /**
     * Publishes both execution contexts for a generated definition option.
     *
     * @param array{M\Statement, int} $parts The complete definition and its SQL condition
     * @return array{\SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, int}
     */
    public static function definitionInput(array $parts, \SqlSemantics\Statement\AnalyzedStatement $definition, \SqlSemantics\Contract\AnalysisContext $missing, \SqlSemantics\Contract\AnalysisContext $declared): array
    {
        [$request, $expected] = $parts;
        return [$definition, \SqlSemantics\Facade\Semantics::fromStatement($missing, $request), \SqlSemantics\Facade\Semantics::fromStatement($declared, \SqlSemantics\Platform\MySql\Construction\Occurrences::fresh($request)), $expected];
    }

    /**
     * Generates option operands whose conditions precede target lookup and definition installation.
     *
     * @return non-empty-list<ErisGenerator<array{M\Statement, int}>>
     */
    public static function definitionForms(GrammarRelease $release, M\Table\CreateTable $create): array
    {
        $names = new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(Generators::map(static fn (int $value): string => 'unknown_' . $value, Operands::sized(Generators::nat(), 1000)));
        $algorithms = new Alter\Modifier\AlgorithmOptionGenerator($names);
        $locks = new Alter\Modifier\LockOptionGenerator($names);
        $engine = new Server\Storage\Option\EngineOptionGenerator(Generators::constant(new Name('InnoDB')));
        $choices = [
            Operands::tuple(Generators::map(static fn (M\Alter\Modifier\AlgorithmOption $option): M\Statement => new M\Alter\AlterTable($create->name, [$option]), $algorithms), Generators::constant(1800)),
            Operands::tuple(Generators::map(static fn (M\Alter\Modifier\LockOption $option): M\Statement => new M\Alter\AlterTable($create->name, [$option]), $locks), Generators::constant(1801)),
            Operands::tuple(new Server\Storage\CreateTablespaceGenerator($names, options: Generators::vector(2, $engine)), Generators::constant(1527)),
        ];
        if (version_compare(substr($release->value, 6), '8.0.16', '>=')) {
            $text = new Literal\TextGenerator(Generators::map(static fn (int $value): string => 'bad_' . $value, Operands::sized(Generators::nat(), 1000)));
            $encryption = new Server\Database\DatabaseEncryptionGenerator($text);
            $choices[] = Operands::tuple(new Server\Database\CreateDatabaseGenerator(Generators::bool(), $names, Generators::vector(1, $encryption)), Generators::constant(1525));
        }
        if (version_compare(substr($release->value, 6), '8.0.21', '>=')) {
            array_push($choices, ...self::attributeForms($create));
        }
        if ($release !== GrammarRelease::MySql5651) {
            array_push($choices, ...Table\Column\GeneratedColumnGenerator::definitionForms($release, $create));
        }
        return $choices;
    }

    /**
     * Composes every JSON attribute role with the same dependent column and table names.
     *
     * @return non-empty-list<ErisGenerator<array{M\Statement, int}>>
     */
    public static function attributeForms(M\Table\CreateTable $create): array
    {
        $column = $create->elements[0];
        \SqlSemantics\Diagnostic\Check::invariant($column instanceof M\Table\Column\ColumnDefinition, 'A generated table supplies its column declaration.');
        $text = new Literal\TextGenerator(Generators::map(static fn (int $value): string => 'bad_' . $value, Operands::sized(Generators::nat(), 1000)));
        $columnAttribute = new Table\Column\EngineAttributeGenerator($text, Generators::bool());
        $columns = Generators::map(static fn (M\Table\Column\EngineAttribute $attribute): M\Table\Column\ColumnDefinition => new M\Table\Column\ColumnDefinition($column->name, new M\Table\Column\OrdinaryColumn($column->specification->dataType(), [$attribute])), $columnAttribute);
        $tableOption = new Table\Option\TextOptionGenerator(Generators::elements([M\Table\Option\Kind\TextOptionKind::EngineAttribute, M\Table\Option\Kind\TextOptionKind::SecondaryEngineAttribute]), $text);
        $indexOption = new Table\Key\Option\IndexEngineAttributeGenerator($text, Generators::bool());
        $key = new M\Table\Key\ColumnPart($column->name->column);
        $forms = [
            Generators::map(static fn (M\Table\Column\ColumnDefinition $element): M\Statement => new M\Table\CreateTable($create->name, [$element]), $columns),
            Generators::map(static fn (M\Table\Column\ColumnDefinition $element): M\Statement => new M\Alter\AlterTable($create->name, [new M\Alter\Column\AddColumn($element)]), $columns),
            Generators::map(static fn (M\Table\Option\TextOption $option): M\Statement => new M\Table\CreateTable($create->name, [$column], [$option]), $tableOption),
            Generators::map(static fn (M\Table\Option\TextOption $option): M\Statement => new M\Alter\AlterTable($create->name, [new M\Alter\Command\SetTableOptions([$option])]), $tableOption),
            Generators::map(static fn (M\Table\Key\Option\IndexEngineAttribute $option): M\Statement => new M\Table\CreateIndex(new Name('generated_index'), $create->name, [$key], options: [$option]), $indexOption),
            Generators::map(static fn (M\Table\Key\Option\IndexEngineAttribute $option): M\Statement => new M\Table\CreateTable($create->name, [$column, new M\Table\Key\IndexDefinition(M\Table\Key\IndexKind::Index, [$key], options: [$option])]), $indexOption),
            new Server\Storage\CreateTablespaceGenerator(Generators::constant($create->name->name), options: Generators::vector(1, new Server\Storage\Option\EngineAttributeOptionGenerator($text))),
        ];
        return array_map(static fn (ErisGenerator $form): ErisGenerator => Operands::tuple($form, Generators::constant(3980)), $forms);
    }

    /**
     * Composes a channel configuration with related requests before and after it exists.
     *
     * @return ErisGenerator<array{\SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, \SqlSemantics\Statement\AnalyzedStatement, int}>
     */
    public static function replicationConditions(GrammarRelease $release): ErisGenerator
    {
        $channels = $release === GrammarRelease::MySql5651 ? Generators::constant(null) : new Literal\TextGenerator(Generators::map(static fn (int $value): string => 'channel' . $value, Generators::choose(0, 1000)));
        return Operands::bind($channels, static function (?M\Literal\Text $channel) use ($release): ErisGenerator {
            $terminology = (new \SqlSemantics\Platform\MySql\Rules\Replication\Releases())->source($release);
            $context = new \SqlSemantics\Contract\AnalysisContext(new \SqlSemantics\Contract\LanguageProfile($release), [new Name('pbt')]);
            $configure = \SqlSemantics\Facade\Semantics::fromStatement($context, new M\Replication\Source\ChangeReplicationSource($terminology, [new M\Replication\Source\SourceOption($terminology, M\Replication\Source\SourceOptionKind::Host, new M\Literal\Text('source.example'))], $channel));
            $choices = [Generators::map(static fn (M\Replication\Replica\ReplicaUntil $until): array => [new M\Replication\Replica\StartReplica($terminology, until: $until, channel: $channel), 1277], Replication\Replica\ReplicaUntilGenerator::conditions($release))];
            foreach (Replication\Source\SourceOptionGenerator::conditions($release) as $option) {
                $choices[] = Generators::map(static fn (array $parts): array => self::replicationSource($parts, $terminology, $channel), $option);
            }
            if (!(new \SqlSemantics\Platform\MySql\Rules\Replication\Releases())->legacy($release)) {
                array_push($choices, ...Replication\Group\StartGroupReplicationGenerator::conditions());
            }
            return Generators::map(static fn (array $parts): array => self::definitionInput($parts, $configure, $context, $context), Operands::oneOf($choices));
        });
    }

    /**
     * Retains the generated source option and the channel it depends on.
     *
     * @param array{M\Replication\Source\SourceOption, int} $parts The option and expected SQL condition
     * @return array{M\Replication\Source\ChangeReplicationSource, int}
     */
    public static function replicationSource(array $parts, M\Replication\Terminology $terminology, ?M\Literal\Text $channel): array
    {
        return [new M\Replication\Source\ChangeReplicationSource($terminology, [$parts[0], new M\Replication\Source\SourceOption($terminology, M\Replication\Source\SourceOptionKind::Port, new M\Literal\Numeral('3307'))], $channel), $parts[1]];
    }

    /**
     * Composes integer-position requests without rendering or reparsing their values.
     *
     * @param 'explain'|'size'|'nodegroup'|'account'|'partition'|'subpartition'|'algorithm'|'weight'|'srid'|'spatial-create'|'spatial-drop'|'organization' $family The numeric operand role
     * @return ErisGenerator<\SqlSemantics\Statement\AnalyzedStatement>
     */
    public static function numericRequests(GrammarRelease $release, string $family): ErisGenerator
    {
        $requests = Generators::map(static function (M\Literal\Numeral $number) use ($family): M\Statement {
            $name = new Name('numeric_request');
            $table = new \SqlSemantics\Statement\Identifier\QualifiedName($name);
            $column = new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('a')), new M\Table\Column\OrdinaryColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int)));
            return match ($family) {
                'explain' => new M\Utility\Explain\ExplainConnection($number),
                'size' => new M\Server\Storage\CreateTablespace($name, options: [new M\Server\Storage\Option\SizeOption(M\Server\Storage\Option\SizeOptionKind::Initial, new M\Literal\ByteSize($number))]),
                'nodegroup' => new M\Server\Storage\CreateTablespace($name, options: [new M\Server\Storage\Option\NodegroupOption($number)]),
                'account' => new M\Account\CreateUser(false, [new M\Account\User\UserSpecification(new M\Name\AccountName($name))], options: [new M\Account\Option\AccountOption(M\Account\Option\AccountOptionKind::ExpireInterval, $number)]),
                'partition' => new M\Table\CreateTable($table, [$column], partitioning: new M\Partition\PartitionClause(new M\Partition\Method\KeyMethod(false, null, [new Name('a')]), $number, null, [])),
                'subpartition' => new M\Table\CreateTable($table, [$column], partitioning: new M\Partition\PartitionClause(new M\Partition\Method\ExpressionMethod(M\Partition\Method\PartitionKind::Range, new M\Literal\NumberLiteral('1')), null, new M\Partition\Subpartitioning(new M\Partition\Method\KeyMethod(false, null, [new Name('a')]), $number), [])),
                'algorithm' => new M\Table\CreateTable($table, [$column], partitioning: new M\Partition\PartitionClause(new M\Partition\Method\KeyMethod(false, $number, [new Name('a')]), null, null, [])),
                'weight' => new M\Dml\Evaluation([new M\Query\SelectExpression(new M\Call\Weight\WeightString(new M\Literal\StringLiteral(['text']), M\Call\Weight\WeightCast::Char, $number))]),
                'srid' => new M\Table\CreateTable($table, [new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('a')), new M\Table\Column\OrdinaryColumn(new M\Type\Spatial(M\Type\Kind\SpatialKind::Point), [new M\Table\Column\SridAttribute($number)]))]),
                'spatial-create' => new M\Server\Spatial\CreateSpatialReference(false, false, $number, []),
                'spatial-drop' => new M\Server\Spatial\DropSpatialReference(false, $number),
                'organization' => new M\Server\Spatial\CreateSpatialReference(false, false, new M\Literal\Numeral('7'), [new M\Server\Spatial\SpatialAttribute(M\Server\Spatial\SpatialAttributeKind::Organization, new M\Literal\Text('example'), $number)]),
            };
        }, Literal\NumeralGenerator::refusedIntegers());
        return new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $requests);
    }


    /**
     * Composes stored numeric requests and preceding assignments with lexical shadowing.
     *
     * @return ErisGenerator<array{\SqlSemantics\Statement\AnalyzedStatement, int}>
     */
    public static function numericDefinitions(GrammarRelease $release): ErisGenerator
    {
        return Generators::map(static function (array $parts) use ($release): array {
            [$number, $deferred, $target, $shadowed, $earlier, $suffix, $same, $following] = $parts;
            $name = new Name('numeric_missing_' . $suffix);
            $subject = $deferred ? new M\Expression\Row([new M\Literal\NullLiteral(), new M\Literal\NullLiteral()]) : new M\Literal\StringLiteral(['value']);
            $expression = $subject instanceof \SqlSemantics\Statement\Scalar
                ? new M\Call\Weight\WeightString($subject, M\Call\Weight\WeightCast::Char, $number)
                : new M\Routine\Program\Expression\Call\DeferredWeightString($subject, M\Call\Weight\WeightCast::Char, $number);
            $numeric = new M\Dml\Evaluation([$expression instanceof \SqlSemantics\Statement\Scalar ? new M\Query\SelectExpression($expression) : new M\Routine\Program\DeferredProjection($expression)]);
            $value = $same && !$following ? $expression : ($target === 'deferred' ? new M\Expression\Row([new M\Literal\NullLiteral(), new M\Literal\NullLiteral()]) : new M\Literal\NumberLiteral('1'));
            $assignment = $value instanceof \SqlSemantics\Statement\Scalar
                ? new M\Utility\Set\SetVariables([$target === 'explicit' ? new M\Utility\Set\SystemAssignment(new M\Variable\SystemTarget($name), $value) : new M\Utility\Set\NameAssignment($name, $value)])
                : new M\Routine\Program\DeferredAssignments([new M\Routine\Program\DeferredAssignment($target === 'explicit' ? new M\Variable\SystemTarget($name) : $name, $value)]);
            if ($following) {
                $tail = $expression instanceof \SqlSemantics\Statement\Scalar
                    ? new M\Utility\Set\UserAssignment(new M\Variable\UserVariable(new Name('numeric_value')), $expression)
                    : new M\Routine\Program\DeferredAssignment(new M\Variable\UserVariable(new Name('numeric_value')), $expression);
                $assignment = $assignment instanceof M\Routine\Program\DeferredAssignments || $tail instanceof M\Routine\Program\DeferredAssignment
                    ? new M\Routine\Program\DeferredAssignments([...$assignment->items, $tail])
                    : new M\Utility\Set\SetVariables([...$assignment->items, $tail]);
            }
            $statements = $same || $following ? [$assignment] : ($earlier ? [$assignment, $numeric] : [$numeric, $assignment]);
            $body = new M\Routine\Program\Block(
                $shadowed ? [new M\Routine\Program\VariableDeclaration([$name], new M\Type\Integral(M\Type\Kind\IntegralKind::Int))] : [],
                [new M\Routine\Program\Flow\IfStatement([new M\Routine\Program\Flow\ConditionalBranch(new M\Literal\NumberLiteral('0'), $statements)])]
            );
            $definition = new M\Routine\CreateProcedure(new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('numeric_' . $suffix)), new M\Routine\ParameterList([]), $body);
            $context = new \SqlSemantics\Contract\AnalysisContext(new \SqlSemantics\Contract\LanguageProfile($release), [new Name('pbt')]);
            $lookupFirst = $same || $following ? $release === GrammarRelease::MySql5651 : $earlier;
            return [(new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($context, $definition), $lookupFirst && (!$shadowed || $target === 'explicit') ? 1193 : 1064];
        }, Operands::tuple(Literal\NumeralGenerator::refusedIntegers(), Generators::bool(), Generators::elements('named', 'explicit', 'deferred'), Generators::bool(), Generators::bool(), Generators::choose(1, 99), Generators::bool(), Generators::bool()));
    }

    /**
     * Composes complete account-bearing requests and their selected-release name conditions.
     *
     * @return ErisGenerator<array{\SqlSemantics\Statement\AnalyzedStatement, int}>
     */
    public static function accountRequests(GrammarRelease $release): ErisGenerator
    {
        return Operands::bind(\Tests\Generator\MySql\Statement\Name\AccountNameGenerator::refusedOperands($release), static function (array $input) use ($release): ErisGenerator {
            [$account, $code] = $input;
            $requests = self::accountStatements($account, $release);
            array_push($requests, ...self::accountDefinitions($account));
            $context = new \SqlSemantics\Contract\AnalysisContext(new \SqlSemantics\Contract\LanguageProfile($release), [new Name('pbt')]);
            return Generators::map(static fn (M\Statement $statement): array => self::accountCondition($context, $statement, $code), Generators::elements($requests));
        });
    }

    /**
     * Selects account commands while preserving each release's structural alternatives.
     *
     * @return non-empty-list<M\Statement>
     */
    public static function accountStatements(M\Name\AccountName $account, GrammarRelease $release): array
    {
        $profile = new \SqlSemantics\Contract\LanguageProfile($release);
        $user = new M\Account\User\UserSpecification($account);
        $current = new M\Name\CurrentUser();
        $grantee = new M\Account\User\Grantee($account);
        $all = [new M\Account\Privilege\Item\AllPrivileges()];
        $global = new M\Account\Privilege\Level\GlobalLevel();
        $table = M\Account\Privilege\Level\ObjectKind::Table;
        $requests = [
            new M\Account\CreateUser(false, [$user]),
            new M\Account\DropUser(false, [$account]),
            new M\Account\RenameUser([new M\Account\User\UserRenaming($current, $account)], $profile),
            new M\Account\SetPassword($account, new M\Account\Password(new M\Literal\Text('secret'))),
            new M\Account\Privilege\GrantPrivileges($all, $table, $global, [$grantee]),
            new M\Account\Privilege\GrantProxy($current, [$grantee]),
            new M\Account\Privilege\RevokeAll(false, [$user]),
            new M\Account\Privilege\RevokePrivileges(false, $all, $table, $global, [$user]),
            new M\Account\Privilege\RevokeProxy(false, $current, [$user]),
            new M\Utility\Show\Server\ShowGrants($account),
        ];
        if ($release === GrammarRelease::MySql5651) {
            $requests[] = new M\Account\ExpireUserPasswords([$account]);
            $requests[] = new M\Utility\Set\SetVariables([new M\Utility\Set\PasswordAssignment(new M\Account\Password(new M\Literal\Text('secret')), $account), new M\Utility\Set\UserAssignment(new M\Variable\UserVariable(new Name('after_password')), new M\Literal\NumberLiteral('1'))]);
        } else {
            $requests[] = new M\Account\AlterUser(false, [$user]);
            $requests[] = new M\Utility\Show\Server\ShowCreateUser($account);
        }
        if (!in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
            $roles = new M\Account\User\RoleSelection(M\Account\User\RoleSet::Named, [$account]);
            array_push(
                $requests,
                new M\Account\CreateRole(false, [$account], $profile),
                new M\Account\DropRole(false, [$account]),
                new M\Account\AlterDefaultRole(false, $current, $roles),
                new M\Account\SetDefaultRole($roles, [new M\Name\AccountName(new Name('root'))]),
                new M\Account\SetRole($roles),
                new M\Account\Privilege\GrantRoles([new M\Account\Privilege\Item\GrantedRole($account)], [$current], profile: $profile),
                new M\Account\Privilege\RevokeRoles(false, [new M\Account\Privilege\Item\GrantedRole($account)], [$current]),
                new M\Account\AlterRegistration(false, $account, M\Account\User\Factor::Second, M\Account\User\RegistrationStep::Initiate),
                new M\Server\Instance\CloneInstance($account, new M\Literal\Numeral('3306'), new M\Literal\Text('secret')),
                new M\Replication\Source\ChangeReplicationSource(M\Replication\Terminology::Current, [new M\Replication\Source\SourceOption(M\Replication\Terminology::Current, M\Replication\Source\SourceOptionKind::PrivilegeChecksUser, $account)])
            );
        }
        return $requests;
    }

    /**
     * Builds all stored-object headers from the same generated account operand.
     *
     * @return non-empty-list<M\Statement>
     */
    public static function accountDefinitions(M\Name\AccountName $account): array
    {
        $name = new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('account_definition'));
        $body = new M\Routine\Program\Block();
        $view = new M\View\ViewDefinition($name, new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))]), definer: $account);
        $schedule = new M\Routine\Event\OnceSchedule(new M\Literal\StringLiteral(['2030-01-01 00:00:00']));
        return [
            new M\Routine\CreateProcedure($name, new M\Routine\ParameterList([]), $body, definer: $account),
            new M\Routine\CreateFunction($name, new M\Routine\ParameterList([]), new M\Type\Integral(M\Type\Kind\IntegralKind::Int), new M\Routine\Definition\FunctionImplementation(new M\Routine\Program\Flow\ReturnStatement(new M\Literal\NumberLiteral('1'))), definer: $account),
            new M\Routine\CreateTrigger($name, M\Routine\Trigger\TriggerTime::Before, M\Routine\Trigger\TriggerEvent::Insert, new M\Routine\Trigger\TriggerTable($name), $body, definer: $account),
            new M\Routine\CreateEvent($name, $schedule, $body, definer: $account),
            new M\Routine\AlterEvent($name, schedule: $schedule, definer: $account),
            new M\View\CreateView($view),
            new M\View\AlterView($view),
        ];
    }

    /**
     * Composes unreachable stored account commands with earlier and later variable failures.
     *
     * @return ErisGenerator<array{\SqlSemantics\Statement\AnalyzedStatement, int}>
     */
    public static function accountPrograms(GrammarRelease $release): ErisGenerator
    {
        return Operands::bind(Operands::tuple(\Tests\Generator\MySql\Statement\Name\AccountNameGenerator::refusedOperands($release), Generators::bool(), Generators::choose(1, 99)), static function (array $parts) use ($release): ErisGenerator {
            [[$account, $code], $earlier, $suffix] = $parts;
            $requests = self::accountStatements($account, $release);
            return Generators::map(static function (M\Statement $request) use ($code, $earlier, $suffix, $release): array {
                $assignment = new M\Utility\Set\SetVariables([new M\Utility\Set\NameAssignment(new Name('account_missing_' . $suffix), new M\Literal\NumberLiteral('1'))]);
                $body = new M\Routine\Program\Block([], [new M\Routine\Program\Flow\IfStatement([new M\Routine\Program\Flow\ConditionalBranch(new M\Literal\NumberLiteral('0'), $earlier ? [$assignment, $request] : [$request, $assignment])])]);
                $statement = new M\Routine\CreateProcedure(new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('account_program_' . $suffix)), new M\Routine\ParameterList([]), $body);
                $context = new \SqlSemantics\Contract\AnalysisContext(new \SqlSemantics\Contract\LanguageProfile($release), [new Name('pbt')]);
                return self::accountCondition($context, $statement, $earlier ? 1193 : $code);
            }, Generators::elements($requests));
        });
    }

    /**
     * Publishes a generated request together with its account-domain condition.
     *
     * @return array{\SqlSemantics\Statement\AnalyzedStatement, int}
     */
    public static function accountCondition(\SqlSemantics\Contract\AnalysisContext $context, M\Statement $statement, int $code): array
    {
        return [(new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($context, $statement), $code];
    }

}
