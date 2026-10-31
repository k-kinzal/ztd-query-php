<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Fact;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use Tests\Generator\MySql\Statement\Table\Problem\Creation\RefusedCreateTableGenerator;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Statement\Fact\Diagnostic;
use Tests\Generator\MySql\Statement\Account\Problem\AccountNameFailureGenerator;
use Tests\Generator\MySql\Statement\Account\Problem\InvalidFactorPairGenerator;
use Tests\Generator\MySql\Statement\Account\Problem\InvalidUserAttributeGenerator;
use Tests\Generator\MySql\Statement\Account\Problem\MisplacedPrivilegeGenerator;
use Tests\Generator\MySql\Statement\Account\Problem\NumberOutOfRangeGenerator;
use Tests\Generator\MySql\Statement\Account\Problem\PriorityOutOfRangeGenerator;
use Tests\Generator\MySql\Statement\Account\Problem\RefusedColumnLevelGenerator;
use Tests\Generator\MySql\Statement\Account\Problem\RejectedPasswordHashGenerator;
use Tests\Generator\MySql\Statement\Account\Problem\RepeatedTlsAttributeGenerator;
use Tests\Generator\MySql\Statement\Account\Problem\ResourceGroupNameTooLongGenerator;
use Tests\Generator\MySql\Statement\Account\Problem\Role\RefusedCreateRoleGenerator;
use Tests\Generator\MySql\Statement\Account\Problem\Role\RefusedGrantRolesGenerator;
use Tests\Generator\MySql\Statement\Account\Problem\RoleOrPrivilegeMismatchGenerator;
use Tests\Generator\MySql\Statement\Account\Problem\RoutineColumnPrivilegeGenerator;
use Tests\Generator\MySql\Statement\Account\Problem\UnknownGrantColumnGenerator;
use Tests\Generator\MySql\Statement\Account\Problem\User\RefusedRenameUserGenerator;
use Tests\Generator\MySql\Statement\Alter\Problem\PartitionEntryRefusedGenerator;
use Tests\Generator\MySql\Statement\Alter\Problem\RepeatedTableGenerator;
use Tests\Generator\MySql\Statement\Alter\Problem\TableExistsGenerator;
use Tests\Generator\MySql\Statement\Alter\Problem\UnknownAlterChoiceGenerator;
use Tests\Generator\MySql\Statement\Alter\Problem\UnknownColumnGenerator;
use Tests\Generator\MySql\Statement\Call\Problem\Aggregate\AggregateNestingConstraintGenerator;
use Tests\Generator\MySql\Statement\Call\Problem\FrameConstraintGenerator;
use Tests\Generator\MySql\Statement\Call\Problem\InvalidJsonDocumentGenerator;
use Tests\Generator\MySql\Statement\Call\Problem\InvalidJsonPathGenerator;
use Tests\Generator\MySql\Statement\Call\Problem\InvalidNamedConstantGenerator;
use Tests\Generator\MySql\Statement\Call\Problem\InvalidWindowCountGenerator;
use Tests\Generator\MySql\Statement\Call\Problem\NamedArgumentGenerator;
use Tests\Generator\MySql\Statement\Call\Problem\ReservedFunctionGenerator;
use Tests\Generator\MySql\Statement\Call\Problem\Type\ArgumentDomainMismatchGenerator;
use Tests\Generator\MySql\Statement\Call\Problem\UnsupportedWindowingGenerator;
use Tests\Generator\MySql\Statement\Call\Problem\Window\NestingConstraintGenerator;
use Tests\Generator\MySql\Statement\Call\Problem\WindowConstraintGenerator;
use Tests\Generator\MySql\Statement\Call\Problem\WindowOperandConstraintGenerator;
use Tests\Generator\MySql\Statement\Call\Problem\WindowRefinementGenerator;
use Tests\Generator\MySql\Statement\Call\Problem\WrongArgumentCountGenerator;
use Tests\Generator\MySql\Statement\Dml\Handler\RefusedReadGenerator;
use Tests\Generator\MySql\Statement\Dml\Handler\RefusedSubqueryGenerator;
use Tests\Generator\MySql\Statement\Dml\Problem\EvaluationWildcardGenerator;
use Tests\Generator\MySql\Statement\Dml\Problem\GeneratedColumnWriteGenerator;
use Tests\Generator\MySql\Statement\Dml\Problem\Insert\InsertGroupingGenerator;
use Tests\Generator\MySql\Statement\Dml\Problem\LoadConflictGenerator;
use Tests\Generator\MySql\Statement\Dml\Problem\Read\OrderingPositionGenerator;
use Tests\Generator\MySql\Statement\Dml\Problem\RepeatedDeleteTargetGenerator;
use Tests\Generator\MySql\Statement\Dml\Problem\UnknownDeleteTableGenerator;
use Tests\Generator\MySql\Statement\Dml\Problem\ValueCountMismatchGenerator;
use Tests\Generator\MySql\Statement\Dml\Problem\WriteMisuseGenerator;
use Tests\Generator\MySql\Statement\Dml\Problem\ZeroFileCountGenerator;
use Tests\Generator\MySql\Statement\Expression\Problem\CharacterCastRangeGenerator;
use Tests\Generator\MySql\Statement\Expression\Problem\CollationMismatchGenerator;
use Tests\Generator\MySql\Statement\Expression\Problem\IllegalCollationMixGenerator;
use Tests\Generator\MySql\Statement\Expression\Problem\InvalidCharacterStringGenerator;
use Tests\Generator\MySql\Statement\Expression\Problem\InvalidJsonCastGenerator;
use Tests\Generator\MySql\Statement\Expression\Problem\NotSupportedYetGenerator;
use Tests\Generator\MySql\Statement\Expression\Problem\OperandColumnsGenerator;
use Tests\Generator\MySql\Statement\Expression\Problem\RefusedNumberLiteralGenerator;
use Tests\Generator\MySql\Statement\Expression\Problem\RefusedTemporalLiteralGenerator;
use Tests\Generator\MySql\Statement\Expression\Problem\SpatialArithmeticGenerator;
use Tests\Generator\MySql\Statement\Expression\Problem\TimeZoneConversionGenerator;
use Tests\Generator\MySql\Statement\Expression\Problem\TooBigPrecisionGenerator;
use Tests\Generator\MySql\Statement\Expression\Problem\UnknownCharsetGenerator;
use Tests\Generator\MySql\Statement\Expression\Problem\UnknownCollationGenerator;
use Tests\Generator\MySql\Statement\Name\AmbiguousAliasGenerator;
use Tests\Generator\MySql\Statement\Name\EmptyObjectNameGenerator;
use Tests\Generator\MySql\Statement\Name\InvalidProjectionAliasGenerator;
use Tests\Generator\MySql\Statement\Name\MissingTableQualifierGenerator;
use Tests\Generator\MySql\Statement\Name\Problem\MismatchedDefinitionNameGenerator;
use Tests\Generator\MySql\Statement\Name\Problem\NameTooLongGenerator;
use Tests\Generator\MySql\Statement\Partition\Problem\InvalidPartitionExpressionGenerator;
use Tests\Generator\MySql\Statement\Partition\Problem\MissingPartitionsGenerator;
use Tests\Generator\MySql\Statement\Partition\Problem\RefusedKeyAlgorithmGenerator;
use Tests\Generator\MySql\Statement\Query\Problem\AggregateInOrderingGenerator;
use Tests\Generator\MySql\Statement\Query\Problem\CountMismatchGenerator;
use Tests\Generator\MySql\Statement\Query\Problem\MisuseGenerator;
use Tests\Generator\MySql\Statement\Query\Problem\NonGroupedColumnGenerator;
use Tests\Generator\MySql\Statement\Query\Problem\Operand\RefusedIntoSetOperandGenerator;
use Tests\Generator\MySql\Statement\Query\Problem\Operand\RefusedSelectionGenerator;
use Tests\Generator\MySql\Statement\Query\Problem\Operand\RefusedSetClauseGenerator;
use Tests\Generator\MySql\Statement\Query\Problem\Operand\UnenclosedUnionGenerator;
use Tests\Generator\MySql\Statement\Query\Problem\Option\RestrictedSelectOptionGenerator;
use Tests\Generator\MySql\Statement\Query\Problem\OrdinalOutOfRangeGenerator;
use Tests\Generator\MySql\Statement\Query\Problem\SelectOptionConflictGenerator;
use Tests\Generator\MySql\Statement\Query\Problem\UndeclaredVariableGenerator;
use Tests\Generator\MySql\Statement\Query\Problem\UnknownPartitionGenerator;
use Tests\Generator\MySql\Statement\Query\Problem\UnknownQualifierGenerator;
use Tests\Generator\MySql\Statement\Query\Problem\UnpartitionedTableGenerator;
use Tests\Generator\MySql\Statement\Replication\Problem\RefusedLogSubqueryGenerator;
use Tests\Generator\MySql\Statement\Replication\Problem\RefusedPurgeOperandGenerator;
use Tests\Generator\MySql\Statement\Replication\Problem\RefusedSettingGenerator;
use Tests\Generator\MySql\Statement\Replication\Problem\RefusedSourceNumberGenerator;
use Tests\Generator\MySql\Statement\Routine\Event\Problem\OperandWidthGenerator;
use Tests\Generator\MySql\Statement\Routine\Problem\AssignedConditionOperandGenerator;
use Tests\Generator\MySql\Statement\Routine\Problem\EmptyEventAlterationGenerator;
use Tests\Generator\MySql\Statement\Routine\Problem\LanguageBodyMismatchGenerator;
use Tests\Generator\MySql\Statement\Routine\Problem\ParameterScopeMismatchGenerator;
use Tests\Generator\MySql\Statement\Routine\Problem\ProgramProblemGenerator;
use Tests\Generator\MySql\Statement\Routine\Problem\RefusedProgramBodyGenerator;
use Tests\Generator\MySql\Statement\Routine\Problem\ScopedLocalAssignmentGenerator;
use Tests\Generator\MySql\Statement\Server\Database\Problem\CharsetConflictGenerator;
use Tests\Generator\MySql\Statement\Server\Database\Problem\InvalidEncryptionGenerator;
use Tests\Generator\MySql\Statement\Server\Database\Problem\InvalidUpgradeNameGenerator;
use Tests\Generator\MySql\Statement\Server\Database\Problem\ReadOnlyConflictGenerator;
use Tests\Generator\MySql\Statement\Server\Problem\BucketCountOutOfRangeGenerator;
use Tests\Generator\MySql\Statement\Server\Problem\FractionalNumberGenerator;
use Tests\Generator\MySql\Statement\Server\Problem\HistogramTablesGenerator;
use Tests\Generator\MySql\Statement\Server\Problem\NonUniqueTableGenerator;
use Tests\Generator\MySql\Statement\Server\Problem\RefusedKillOperandGenerator;
use Tests\Generator\MySql\Statement\Server\Problem\RefusedSpatialGenerator;
use Tests\Generator\MySql\Statement\Server\Problem\RefusedXaIdentifierGenerator;
use Tests\Generator\MySql\Statement\Server\Problem\SpatialProblemGenerator;
use Tests\Generator\MySql\Statement\Server\Problem\StorageProblemGenerator;
use Tests\Generator\MySql\Statement\Server\Problem\UnknownHistogramColumnGenerator;
use Tests\Generator\MySql\Statement\Table\Problem\Column\SridRangeGenerator;
use Tests\Generator\MySql\Statement\Table\Problem\Creation\DuplicateKeyColumnGenerator;
use Tests\Generator\MySql\Statement\Table\Problem\Creation\FunctionalIndexKindGenerator;
use Tests\Generator\MySql\Statement\Table\Problem\Creation\InvalidIndexNameGenerator;
use Tests\Generator\MySql\Statement\Table\Problem\Creation\RefusedCreateTailGenerator;
use Tests\Generator\MySql\Statement\Table\Problem\Creation\SpatialKeyArityGenerator;
use Tests\Generator\MySql\Statement\Table\Problem\ExplicitIndexOrderGenerator;
use Tests\Generator\MySql\Statement\Table\Problem\Generation\InvalidAttributeGenerator;
use Tests\Generator\MySql\Statement\Table\Problem\IncorrectColumnNameGenerator;
use Tests\Generator\MySql\Statement\Table\Problem\InvalidColumnAttributeGenerator;
use Tests\Generator\MySql\Statement\Table\Problem\MultiplePrimaryKeysGenerator;
use Tests\Generator\MySql\Statement\Table\Problem\NoColumnsGenerator;
use Tests\Generator\MySql\Statement\Table\Problem\NullablePrimaryKeyGenerator;
use Tests\Generator\MySql\Statement\Table\Problem\RefusedJsonAttributeGenerator;
use Tests\Generator\MySql\Statement\Table\Problem\UnattachedEnforcementGenerator;
use Tests\Generator\MySql\Statement\Table\Problem\UnknownIndexParserGenerator;
use Tests\Generator\MySql\Statement\Table\Problem\UnknownKeyColumnGenerator;
use Tests\Generator\MySql\Statement\Table\Problem\ViewColumnCountGenerator;
use Tests\Generator\MySql\Statement\Table\Problem\WrongRelationKindGenerator;
use Tests\Generator\MySql\Statement\Table\Problem\ZeroKeyPrefixGenerator;
use Tests\Generator\MySql\Statement\Type\Problem\CastSizeInputGenerator;
use Tests\Generator\MySql\Statement\Type\Problem\InvalidTypeSizeGenerator;
use Tests\Generator\MySql\Statement\Utility\Problem\GeneratedColumnEntryGenerator;
use Tests\Generator\MySql\Statement\Utility\Problem\InvalidExplainTargetGenerator;
use Tests\Generator\MySql\Statement\Utility\Problem\RefusedConnectionExplainGenerator;
use Tests\Generator\MySql\Statement\Utility\Problem\RefusedExplainGenerator;
use Tests\Generator\MySql\Statement\Utility\Problem\RefusedExplainIntoGenerator;
use Tests\Generator\MySql\Statement\Utility\Problem\RefusedNamesAssignmentGenerator;
use Tests\Generator\MySql\Statement\Utility\Problem\UnknownExplainFormatGenerator;
use Tests\Generator\MySql\Statement\Utility\Problem\UtilityMisuseGenerator;
use Tests\Generator\MySql\Statement\Utility\Show\RefusedFilterGenerator;
use Tests\Generator\MySql\Statement\Variable\Problem\UnknownSystemVariableGenerator;
use Tests\Generator\MySql\Statement\Variable\Problem\UnstructuredVariableGenerator;
use Tests\Generator\MySql\Statement\Variable\Problem\VariableMisuseGenerator;
use Tests\Generator\MySql\Statement\View\Problem\RefusedBodyGenerator;
use Tests\Generator\MySql\Statement\View\Problem\RefusedColumnsGenerator;
use Tests\Generator\MySql\Statement\View\Problem\RefusedExistenceClauseGenerator;
use Tests\Generator\Semantics\Statement\Reference\Column\AmbiguousColumnGenerator;
use Tests\Generator\Semantics\Statement\Reference\Column\MissingColumnGenerator;
use Tests\Generator\Semantics\Statement\Reference\Table\ConflictingTablesGenerator;
use Tests\Generator\Semantics\Statement\Reference\Table\MissingTableGenerator;

/**
 * Generates Diagnostic values from the generators of its operands.
 *
 * @implements ErisGenerator<Diagnostic>
 */
final class DiagnosticGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Diagnostic> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of Diagnostic from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = $depth > 0
            ? Generators::bind(Generators::elements(array_keys(array_filter([511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 508, 508, 511, 508, 511, 511, 511, 511, 508, 508, 508, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 504, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
                0 => new AccountNameFailureGenerator($release),
                1 => new InvalidFactorPairGenerator($release),
                2 => new InvalidUserAttributeGenerator($release),
                3 => new MisplacedPrivilegeGenerator($release),
                4 => new NumberOutOfRangeGenerator($release),
                5 => new PriorityOutOfRangeGenerator($release),
                6 => new RefusedColumnLevelGenerator($release),
                7 => new RejectedPasswordHashGenerator($release),
                8 => new RepeatedTlsAttributeGenerator($release),
                9 => new ResourceGroupNameTooLongGenerator($release),
                10 => new RoleOrPrivilegeMismatchGenerator($release),
                11 => new RefusedCreateRoleGenerator($release),
                12 => new RefusedGrantRolesGenerator($release),
                13 => new RoutineColumnPrivilegeGenerator($release),
                14 => new UnknownGrantColumnGenerator($release),
                15 => new RefusedRenameUserGenerator($release),
                16 => new \Tests\Generator\MySql\Statement\Alter\Problem\DuplicateColumnGenerator($release),
                17 => new PartitionEntryRefusedGenerator($release),
                18 => new RepeatedTableGenerator($release),
                19 => new TableExistsGenerator($release),
                20 => new UnknownAlterChoiceGenerator($release),
                21 => new UnknownColumnGenerator($release),
                22 => new AggregateNestingConstraintGenerator($release),
                23 => new FrameConstraintGenerator($release),
                24 => new InvalidJsonDocumentGenerator($release),
                25 => new InvalidJsonPathGenerator($release),
                26 => new InvalidNamedConstantGenerator($release),
                27 => new InvalidWindowCountGenerator($release),
                28 => new NamedArgumentGenerator($release),
                29 => new ReservedFunctionGenerator($release),
                30 => new ArgumentDomainMismatchGenerator($release),
                31 => new UnsupportedWindowingGenerator($release),
                32 => new WindowConstraintGenerator($release),
                33 => new WindowOperandConstraintGenerator($release),
                34 => new WindowRefinementGenerator($release),
                35 => new NestingConstraintGenerator($release),
                36 => new WrongArgumentCountGenerator($release),
                37 => new RefusedReadGenerator($release),
                38 => new RefusedSubqueryGenerator($release),
                39 => new \Tests\Generator\MySql\Statement\Alter\Problem\DuplicateColumnGenerator($release),
                40 => new EvaluationWildcardGenerator($release),
                41 => new GeneratedColumnWriteGenerator($release),
                42 => new InsertGroupingGenerator($release),
                43 => new LoadConflictGenerator($release),
                44 => new OrderingPositionGenerator($release),
                45 => new RepeatedDeleteTargetGenerator($release),
                46 => new UnknownDeleteTableGenerator($release),
                47 => new ValueCountMismatchGenerator($release),
                48 => new WriteMisuseGenerator($release),
                49 => new ZeroFileCountGenerator($release),
                50 => new CharacterCastRangeGenerator($release),
                51 => new CollationMismatchGenerator($release),
                52 => new IllegalCollationMixGenerator($release),
                53 => new InvalidCharacterStringGenerator($release),
                54 => new InvalidJsonCastGenerator($release),
                55 => new NotSupportedYetGenerator($release),
                56 => new OperandColumnsGenerator($release),
                57 => new RefusedNumberLiteralGenerator($release),
                58 => new \Tests\Generator\MySql\Statement\Expression\Problem\RefusedScalarGenerator($release),
                59 => new RefusedTemporalLiteralGenerator($release),
                60 => new SpatialArithmeticGenerator($release),
                61 => new TimeZoneConversionGenerator($release),
                62 => new TooBigPrecisionGenerator($release),
                63 => new UnknownCharsetGenerator($release),
                64 => new UnknownCollationGenerator($release),
                65 => new AmbiguousAliasGenerator($release, $depth - 1),
                66 => new EmptyObjectNameGenerator($release),
                67 => new InvalidProjectionAliasGenerator($release),
                68 => new MissingTableQualifierGenerator($release),
                69 => new MismatchedDefinitionNameGenerator($release),
                70 => new NameTooLongGenerator($release),
                71 => new InvalidPartitionExpressionGenerator($release),
                72 => new MissingPartitionsGenerator($release),
                73 => new RefusedKeyAlgorithmGenerator($release),
                74 => new \Tests\Generator\MySql\Statement\Expression\Problem\RefusedScalarGenerator($release),
                75 => new AggregateInOrderingGenerator($release),
                76 => new CountMismatchGenerator($release),
                77 => new MisuseGenerator($release),
                78 => new NonGroupedColumnGenerator($release),
                79 => new RefusedIntoSetOperandGenerator($release),
                80 => new \Tests\Generator\MySql\Statement\Query\Problem\Operand\RefusedQueryGenerator($release),
                81 => new RefusedSelectionGenerator($release),
                82 => new RefusedSetClauseGenerator($release),
                83 => new UnenclosedUnionGenerator($release),
                84 => new RestrictedSelectOptionGenerator($release),
                85 => new OrdinalOutOfRangeGenerator($release),
                86 => new SelectOptionConflictGenerator($release),
                87 => new UndeclaredVariableGenerator($release),
                88 => new UnknownPartitionGenerator($release),
                89 => new UnknownQualifierGenerator($release),
                90 => new UnpartitionedTableGenerator($release),
                91 => new RefusedLogSubqueryGenerator($release),
                92 => new RefusedPurgeOperandGenerator($release),
                93 => new RefusedSettingGenerator($release),
                94 => new RefusedSourceNumberGenerator($release),
                95 => new OperandWidthGenerator($release),
                96 => new \Tests\Generator\MySql\Statement\Routine\Event\Problem\RefusedOperandGenerator($release),
                97 => new AssignedConditionOperandGenerator($release),
                98 => new EmptyEventAlterationGenerator($release),
                99 => new LanguageBodyMismatchGenerator($release),
                100 => new ParameterScopeMismatchGenerator($release),
                101 => new ProgramProblemGenerator($release),
                102 => new RefusedProgramBodyGenerator($release),
                103 => new ScopedLocalAssignmentGenerator($release),
                104 => new CharsetConflictGenerator($release),
                105 => new InvalidEncryptionGenerator($release),
                106 => new InvalidUpgradeNameGenerator($release),
                107 => new ReadOnlyConflictGenerator($release),
                108 => new BucketCountOutOfRangeGenerator($release),
                109 => new FractionalNumberGenerator($release),
                110 => new HistogramTablesGenerator($release),
                111 => new NonUniqueTableGenerator($release),
                112 => new RefusedKillOperandGenerator($release),
                113 => new RefusedSpatialGenerator($release),
                114 => new RefusedXaIdentifierGenerator($release),
                115 => new SpatialProblemGenerator($release),
                116 => new StorageProblemGenerator($release),
                117 => new UnknownHistogramColumnGenerator($release),
                118 => new SridRangeGenerator($release),
                119 => new DuplicateKeyColumnGenerator($release),
                120 => new FunctionalIndexKindGenerator($release),
                121 => new InvalidIndexNameGenerator($release),
                122 => new RefusedCreateTailGenerator($release, $depth - 1),
                123 => new SpatialKeyArityGenerator($release),
                124 => new \Tests\Generator\MySql\Statement\Alter\Problem\DuplicateColumnGenerator($release),
                125 => new ExplicitIndexOrderGenerator($release),
                126 => new InvalidAttributeGenerator($release),
                127 => new \Tests\Generator\MySql\Statement\Routine\Event\Problem\RefusedOperandGenerator($release),
                128 => new IncorrectColumnNameGenerator($release),
                129 => new InvalidColumnAttributeGenerator($release),
                130 => new MultiplePrimaryKeysGenerator($release),
                131 => new NoColumnsGenerator($release),
                132 => new NullablePrimaryKeyGenerator($release),
                133 => new RefusedJsonAttributeGenerator($release),
                134 => new UnattachedEnforcementGenerator($release),
                135 => new UnknownIndexParserGenerator($release),
                136 => new UnknownKeyColumnGenerator($release),
                137 => new ViewColumnCountGenerator($release),
                138 => new WrongRelationKindGenerator($release),
                139 => new ZeroKeyPrefixGenerator($release),
                140 => new CastSizeInputGenerator($release),
                141 => new InvalidTypeSizeGenerator($release),
                142 => new GeneratedColumnEntryGenerator($release),
                143 => new InvalidExplainTargetGenerator($release),
                144 => new RefusedConnectionExplainGenerator($release),
                145 => new RefusedExplainGenerator($release),
                146 => new RefusedExplainIntoGenerator($release),
                147 => new RefusedNamesAssignmentGenerator($release),
                148 => new UnknownExplainFormatGenerator($release),
                149 => new UtilityMisuseGenerator($release),
                150 => new RefusedFilterGenerator($release),
                151 => new UnknownSystemVariableGenerator($release),
                152 => new UnstructuredVariableGenerator($release),
                153 => new VariableMisuseGenerator($release),
                154 => new RefusedBodyGenerator($release),
                155 => new RefusedColumnsGenerator($release),
                156 => new RefusedExistenceClauseGenerator($release),
                157 => new \Tests\Generator\MySql\Statement\Query\Problem\Operand\RefusedQueryGenerator($release),
                158 => new AmbiguousColumnGenerator($release, $depth - 1),
                159 => new MissingColumnGenerator($release),
                160 => new ConflictingTablesGenerator($release),
                161 => new MissingTableGenerator($release),
                162 => new RefusedCreateTableGenerator($release, $depth - 1),
                default => throw new LogicException('Unreachable alternative.'),
            })
            : Generators::bind(Generators::elements(array_keys(array_filter([511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 508, 508, 511, 508, 511, 511, 511, 511, 508, 508, 508, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 508, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 504, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
                0 => new AccountNameFailureGenerator($release),
                1 => new InvalidFactorPairGenerator($release),
                2 => new InvalidUserAttributeGenerator($release),
                3 => new MisplacedPrivilegeGenerator($release),
                4 => new NumberOutOfRangeGenerator($release),
                5 => new PriorityOutOfRangeGenerator($release),
                6 => new RefusedColumnLevelGenerator($release),
                7 => new RejectedPasswordHashGenerator($release),
                8 => new RepeatedTlsAttributeGenerator($release),
                9 => new ResourceGroupNameTooLongGenerator($release),
                10 => new RoleOrPrivilegeMismatchGenerator($release),
                11 => new RefusedCreateRoleGenerator($release),
                12 => new RefusedGrantRolesGenerator($release),
                13 => new RoutineColumnPrivilegeGenerator($release),
                14 => new UnknownGrantColumnGenerator($release),
                15 => new RefusedRenameUserGenerator($release),
                16 => new \Tests\Generator\MySql\Statement\Alter\Problem\DuplicateColumnGenerator($release),
                17 => new PartitionEntryRefusedGenerator($release),
                18 => new RepeatedTableGenerator($release),
                19 => new TableExistsGenerator($release),
                20 => new UnknownAlterChoiceGenerator($release),
                21 => new UnknownColumnGenerator($release),
                22 => new AggregateNestingConstraintGenerator($release),
                23 => new FrameConstraintGenerator($release),
                24 => new InvalidJsonDocumentGenerator($release),
                25 => new InvalidJsonPathGenerator($release),
                26 => new InvalidNamedConstantGenerator($release),
                27 => new InvalidWindowCountGenerator($release),
                28 => new NamedArgumentGenerator($release),
                29 => new ReservedFunctionGenerator($release),
                30 => new ArgumentDomainMismatchGenerator($release),
                31 => new UnsupportedWindowingGenerator($release),
                32 => new WindowConstraintGenerator($release),
                33 => new WindowOperandConstraintGenerator($release),
                34 => new WindowRefinementGenerator($release),
                35 => new NestingConstraintGenerator($release),
                36 => new WrongArgumentCountGenerator($release),
                37 => new RefusedReadGenerator($release),
                38 => new RefusedSubqueryGenerator($release),
                39 => new \Tests\Generator\MySql\Statement\Alter\Problem\DuplicateColumnGenerator($release),
                40 => new EvaluationWildcardGenerator($release),
                41 => new GeneratedColumnWriteGenerator($release),
                42 => new InsertGroupingGenerator($release),
                43 => new LoadConflictGenerator($release),
                44 => new OrderingPositionGenerator($release),
                45 => new RepeatedDeleteTargetGenerator($release),
                46 => new UnknownDeleteTableGenerator($release),
                47 => new ValueCountMismatchGenerator($release),
                48 => new WriteMisuseGenerator($release),
                49 => new ZeroFileCountGenerator($release),
                50 => new CharacterCastRangeGenerator($release),
                51 => new CollationMismatchGenerator($release),
                52 => new IllegalCollationMixGenerator($release),
                53 => new InvalidCharacterStringGenerator($release),
                54 => new InvalidJsonCastGenerator($release),
                55 => new NotSupportedYetGenerator($release),
                56 => new OperandColumnsGenerator($release),
                57 => new RefusedNumberLiteralGenerator($release),
                58 => new \Tests\Generator\MySql\Statement\Expression\Problem\RefusedScalarGenerator($release),
                59 => new RefusedTemporalLiteralGenerator($release),
                60 => new SpatialArithmeticGenerator($release),
                61 => new TimeZoneConversionGenerator($release),
                62 => new TooBigPrecisionGenerator($release),
                63 => new UnknownCharsetGenerator($release),
                64 => new UnknownCollationGenerator($release),
                65 => new EmptyObjectNameGenerator($release),
                66 => new InvalidProjectionAliasGenerator($release),
                67 => new MissingTableQualifierGenerator($release),
                68 => new MismatchedDefinitionNameGenerator($release),
                69 => new NameTooLongGenerator($release),
                70 => new InvalidPartitionExpressionGenerator($release),
                71 => new MissingPartitionsGenerator($release),
                72 => new RefusedKeyAlgorithmGenerator($release),
                73 => new \Tests\Generator\MySql\Statement\Expression\Problem\RefusedScalarGenerator($release),
                74 => new AggregateInOrderingGenerator($release),
                75 => new CountMismatchGenerator($release),
                76 => new MisuseGenerator($release),
                77 => new NonGroupedColumnGenerator($release),
                78 => new RefusedIntoSetOperandGenerator($release),
                79 => new \Tests\Generator\MySql\Statement\Query\Problem\Operand\RefusedQueryGenerator($release),
                80 => new RefusedSelectionGenerator($release),
                81 => new RefusedSetClauseGenerator($release),
                82 => new UnenclosedUnionGenerator($release),
                83 => new RestrictedSelectOptionGenerator($release),
                84 => new OrdinalOutOfRangeGenerator($release),
                85 => new SelectOptionConflictGenerator($release),
                86 => new UndeclaredVariableGenerator($release),
                87 => new UnknownPartitionGenerator($release),
                88 => new UnknownQualifierGenerator($release),
                89 => new UnpartitionedTableGenerator($release),
                90 => new RefusedLogSubqueryGenerator($release),
                91 => new RefusedPurgeOperandGenerator($release),
                92 => new RefusedSettingGenerator($release),
                93 => new RefusedSourceNumberGenerator($release),
                94 => new OperandWidthGenerator($release),
                95 => new \Tests\Generator\MySql\Statement\Routine\Event\Problem\RefusedOperandGenerator($release),
                96 => new AssignedConditionOperandGenerator($release),
                97 => new EmptyEventAlterationGenerator($release),
                98 => new LanguageBodyMismatchGenerator($release),
                99 => new ParameterScopeMismatchGenerator($release),
                100 => new ProgramProblemGenerator($release),
                101 => new RefusedProgramBodyGenerator($release),
                102 => new ScopedLocalAssignmentGenerator($release),
                103 => new CharsetConflictGenerator($release),
                104 => new InvalidEncryptionGenerator($release),
                105 => new InvalidUpgradeNameGenerator($release),
                106 => new ReadOnlyConflictGenerator($release),
                107 => new BucketCountOutOfRangeGenerator($release),
                108 => new FractionalNumberGenerator($release),
                109 => new HistogramTablesGenerator($release),
                110 => new NonUniqueTableGenerator($release),
                111 => new RefusedKillOperandGenerator($release),
                112 => new RefusedSpatialGenerator($release),
                113 => new RefusedXaIdentifierGenerator($release),
                114 => new SpatialProblemGenerator($release),
                115 => new StorageProblemGenerator($release),
                116 => new UnknownHistogramColumnGenerator($release),
                117 => new SridRangeGenerator($release),
                118 => new DuplicateKeyColumnGenerator($release),
                119 => new FunctionalIndexKindGenerator($release),
                120 => new InvalidIndexNameGenerator($release),
                121 => new SpatialKeyArityGenerator($release),
                122 => new \Tests\Generator\MySql\Statement\Alter\Problem\DuplicateColumnGenerator($release),
                123 => new ExplicitIndexOrderGenerator($release),
                124 => new InvalidAttributeGenerator($release),
                125 => new \Tests\Generator\MySql\Statement\Routine\Event\Problem\RefusedOperandGenerator($release),
                126 => new IncorrectColumnNameGenerator($release),
                127 => new InvalidColumnAttributeGenerator($release),
                128 => new MultiplePrimaryKeysGenerator($release),
                129 => new NoColumnsGenerator($release),
                130 => new NullablePrimaryKeyGenerator($release),
                131 => new RefusedJsonAttributeGenerator($release),
                132 => new UnattachedEnforcementGenerator($release),
                133 => new UnknownIndexParserGenerator($release),
                134 => new UnknownKeyColumnGenerator($release),
                135 => new ViewColumnCountGenerator($release),
                136 => new WrongRelationKindGenerator($release),
                137 => new ZeroKeyPrefixGenerator($release),
                138 => new CastSizeInputGenerator($release),
                139 => new InvalidTypeSizeGenerator($release),
                140 => new GeneratedColumnEntryGenerator($release),
                141 => new InvalidExplainTargetGenerator($release),
                142 => new RefusedConnectionExplainGenerator($release),
                143 => new RefusedExplainGenerator($release),
                144 => new RefusedExplainIntoGenerator($release),
                145 => new RefusedNamesAssignmentGenerator($release),
                146 => new UnknownExplainFormatGenerator($release),
                147 => new UtilityMisuseGenerator($release),
                148 => new RefusedFilterGenerator($release),
                149 => new UnknownSystemVariableGenerator($release),
                150 => new UnstructuredVariableGenerator($release),
                151 => new VariableMisuseGenerator($release),
                152 => new RefusedBodyGenerator($release),
                153 => new RefusedColumnsGenerator($release),
                154 => new RefusedExistenceClauseGenerator($release),
                155 => new \Tests\Generator\MySql\Statement\Query\Problem\Operand\RefusedQueryGenerator($release),
                156 => new MissingColumnGenerator($release),
                157 => new ConflictingTablesGenerator($release),
                158 => new MissingTableGenerator($release),
                159 => new RefusedCreateTableGenerator($release, $depth - 1),
                default => throw new LogicException('Unreachable alternative.'),
            });
        $this->delegate = Generators::suchThat(
            static function (Diagnostic $value) use ($release): bool {
                $domain = new ReleaseDomain();
                $transition = (new Generations())->transition($value);

                return ($domain->of($value) & $domain->bit($release)) !== 0
                    && (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? !(new ModernForms())->requires($value) : !(new LegacyForms())->requires($value))
                    && ($transition === 0 || ($transition & ($release === GrammarRelease::MySql5651 ? 1 : 2)) !== 0)
                    && ((new LexicalModes())->requirement($value) & 10) === 0;
            },
            $this->delegate
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<Diagnostic>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<Diagnostic>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
