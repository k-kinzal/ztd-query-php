<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Fact;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Diagnostic values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Fact\Diagnostic>
 */
final class DiagnosticGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Fact\Diagnostic> */
    use Composition;

    /**
     * @param non-empty-list<ErisGenerator<\SqlSemantics\Statement\Fact\Diagnostic>> $alternatives Concrete SQL forms satisfying this role
     */
    public function __construct(array $alternatives)
    {
        $this->delegate = \Tests\Generator\Operands::oneOf($alternatives);
    }
    /**
     * Supplies the typed conditions consumed by input refusal and execution diagnostics.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Statement\Fact\Diagnostic>}>
     */
    public static function executionForms(): iterable
    {
        yield from \Tests\Generator\MySql\Statement\Name\MissingTableQualifierGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Query\Problem\MisuseGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Query\Problem\RecursiveReferenceGenerator::executionForms();
        yield from \Tests\Generator\Semantics\Statement\Reference\Column\MissingColumnGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Variable\Problem\UnknownSystemVariableGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Query\Problem\Operand\RefusedSelectionGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Dml\Problem\DuplicateColumnGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Dml\Problem\ValueCountMismatchGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Dml\Problem\WriteMisuseGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Dml\Problem\LoadConflictGenerator::executionForms();
        yield from \Tests\Generator\Semantics\Statement\Reference\Column\AmbiguousColumnGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Routine\Problem\ProgramProblemGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Routine\Problem\RefusedProgramBodyGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Routine\Problem\EmptyEventAlterationGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Routine\Problem\RefusedSignalItemsGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Query\Problem\UnknownQualifierGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Utility\Problem\InvalidExplainTargetGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Query\Problem\CountMismatchGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Expression\Problem\NotSupportedYetGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Expression\Problem\UnknownCollationGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Server\Problem\NonUniqueTableGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Server\Problem\SpatialProblemGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Alter\Problem\UnknownAlterChoiceGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Table\Problem\Generation\RefusedOperandGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Expression\Problem\UnknownCharsetGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Expression\Problem\CollationMismatchGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Call\Problem\InvalidJsonPathGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Call\Problem\InvalidJsonDocumentGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Table\Problem\Creation\FunctionalIndexKindGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Table\Problem\NoColumnsGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Table\Problem\MultiplePrimaryKeysGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Table\Problem\ViewColumnCountGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Table\Problem\DuplicateColumnGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Replication\Problem\RefusedSettingGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Replication\Problem\RefusedPurgeOperandGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Expression\Problem\IllegalCollationMixGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Expression\Problem\RefusedScalarGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Expression\Problem\OperandColumnsGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Partition\Problem\MissingPartitionsGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Account\Problem\NumberOutOfRangeGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Account\Problem\MisplacedPrivilegeGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Name\InvalidProjectionAliasGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Name\AmbiguousAliasGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Query\Problem\Operand\RefusedQueryGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Query\Problem\UndeclaredVariableGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Utility\Problem\RefusedExplainGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Server\Problem\FractionalNumberGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Utility\Problem\UtilityMisuseGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Variable\Problem\VariableMisuseGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Alter\Problem\RepeatedTableGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Expression\Problem\RefusedNumberLiteralGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Expression\Problem\RefusedTemporalLiteralGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Table\Problem\RefusedJsonAttributeGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Name\EmptyObjectNameGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Table\Problem\Column\SridRangeGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Table\Problem\InvalidColumnAttributeGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Table\Problem\ZeroKeyPrefixGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Table\Problem\ExplicitIndexOrderGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\View\Problem\RefusedColumnsGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\View\Problem\RefusedBodyGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\View\Problem\BodyRestrictionGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Dml\Handler\RefusedSubqueryGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Dml\Handler\RefusedReadGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Dml\Problem\RefusedDeleteTargetsGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Dml\Problem\RepeatedDeleteTargetGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Dml\Problem\Delete\RefusedDeleteGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Dml\Problem\ZeroFileCountGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Routine\Event\Problem\RefusedOperandGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Server\Problem\RefusedXaIdentifierGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Server\Database\Problem\InvalidEncryptionGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Server\Problem\RefusedKillOperandGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Call\Problem\UnsupportedWindowingGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Replication\Problem\RefusedLogSubqueryGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Expression\Problem\TooBigPrecisionGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Account\Problem\RoutineColumnPrivilegeGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Account\Problem\RoleOrPrivilegeMismatchGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Account\Problem\RefusedGrantLevelGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Query\Problem\Option\RestrictedSelectOptionGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Dml\Problem\Update\ConflictingUpdateGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Dml\Problem\Read\OrderingPositionGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Call\Problem\WindowConstraintGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Utility\Problem\RefusedConnectionExplainGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Utility\Show\RefusedFilterGenerator::executionForms();
        yield from \Tests\Generator\Semantics\Statement\Reference\Table\MissingTableGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Alter\Problem\TableExistsGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Call\Problem\WrongArgumentCountGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Table\Problem\NullablePrimaryKeyGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Dml\Problem\GeneratedColumnWriteGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Table\Problem\UnknownKeyColumnGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Table\Problem\IncorrectColumnNameGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Table\Problem\Creation\DuplicateKeyColumnGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Dml\Problem\UnknownDeleteTableGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Server\Database\Problem\ReadOnlyConflictGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Server\Problem\StorageProblemGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Type\Problem\InvalidTypeSizeGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Call\Problem\InvalidNamedConstantGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Query\Problem\SelectOptionConflictGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Query\Problem\NonGroupedColumnGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Query\Problem\AggregateInOrderingGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Utility\Problem\RefusedExplainIntoGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Variable\Problem\UnstructuredVariableGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Query\Problem\Operand\UnenclosedUnionGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Account\Problem\RefusedColumnLevelGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Server\Database\Problem\InvalidUpgradeNameGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Query\Problem\Operand\RefusedSetClauseGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Utility\Problem\UnknownExplainFormatGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Account\Problem\RejectedPasswordHashGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\View\Problem\RefusedExistenceClauseGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Dml\Problem\EvaluationWildcardGenerator::executionForms();
        foreach (\Tests\Generator\MySql\Statement\Routine\Event\Problem\OperandWidthGenerator::executionForms() as $name => [$release, $problem]) {
            yield $name => [$release, new \Tests\Generator\MySql\Statement\Routine\Event\Problem\RefusedOperandGenerator(new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator()), $problem)];
        }
        yield from \Tests\Generator\MySql\Statement\Routine\Problem\RefusedProgramBodyGenerator::nestedConditions();
        yield from \Tests\Generator\MySql\Statement\Server\Problem\BucketCountOutOfRangeGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Alter\Problem\UnknownColumnGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Alter\Problem\PartitionEntryRefusedGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Call\Problem\ReservedFunctionGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Call\Problem\Type\ArgumentDomainMismatchGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Call\Problem\NamedArgumentGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Call\Problem\InvalidWindowCountGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Table\Problem\UnattachedEnforcementGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Table\Problem\UnknownIndexParserGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Table\Problem\WrongRelationKindGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Expression\Problem\InvalidCharacterStringGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Expression\Problem\CharacterCastRangeGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Expression\Problem\SpatialArithmeticGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Expression\Problem\InvalidJsonCastGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Account\Problem\InvalidFactorPairGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Account\Problem\PriorityOutOfRangeGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Account\Problem\AccountNameFailureGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Account\Problem\RepeatedTlsAttributeGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Query\Problem\OrdinalOutOfRangeGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Query\Problem\UnknownPartitionGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Query\Problem\UnpartitionedTableGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Utility\Problem\RefusedNamesAssignmentGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Utility\Problem\GeneratedColumnEntryGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Table\Problem\Creation\RefusedCreateTailGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Replication\Problem\RefusedSourceNumberGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Name\Problem\MismatchedDefinitionNameGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Partition\Problem\InvalidPartitionExpressionGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Server\Problem\RefusedSpatialGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Table\Problem\Creation\InvalidIndexNameGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Dml\Problem\Insert\InsertGroupingGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Type\Problem\CastSizeInputGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Call\Problem\WindowRefinementGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Call\Problem\Aggregate\AggregateNestingConstraintGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Call\Problem\WindowOperandConstraintGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Call\Problem\Window\NestingConstraintGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Call\Problem\FrameConstraintGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Table\Problem\Creation\SpatialKeyArityGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\View\Problem\RefusedQueryGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Partition\Problem\RefusedScalarGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Partition\Problem\RefusedKeyAlgorithmGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Utility\Problem\RefusedExplainTargetsGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Name\Problem\NameTooLongGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Account\Problem\ResourceGroupNameTooLongGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Partition\Problem\DefinitionIssueGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Server\Database\Problem\CharsetConflictGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Expression\Problem\TimeZoneConversionGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Replication\Problem\RefusedGroupStartGenerator::executionForms();
        yield from \Tests\Generator\MySql\Statement\Query\Problem\Operand\RefusedIntoSetOperandGenerator::executionForms();
    }

}
