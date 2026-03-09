<?php

declare(strict_types=1);

namespace Spec\Sqlite;

use Faker\Factory;
use Faker\Generator as FakerGenerator;
use LogicException;
use SqlFaker\Contract\GenerationRequest;
use SqlFaker\Contract\Grammar as ContractGrammar;
use SqlFaker\Sqlite\StatementType;
use SqlFaker\SqliteProvider;
use Spec\Subject\AbstractSupportedLanguage;
use Spec\Subject\FamilyDefinition;
use Spec\Subject\FamilyRequest;
use Spec\Subject\SqlWitness;

/**
 * Spec harness for SQLite family-based checks.
 */
final class SupportedLanguage extends AbstractSupportedLanguage
{
    private FakerGenerator $faker;
    private SqliteProvider $provider;

    public function __construct()
    {
        $this->faker = Factory::create();
        $this->provider = new SqliteProvider($this->faker);
    }

    /**
     * Returns the SQL dialect served by this subject.
     */
    public function dialect(): string
    {
        return 'sqlite';
    }

    public function supportedGrammar(): ContractGrammar
    {
        return $this->provider->supportedGrammar();
    }

    public function entryRules(): array
    {
        return [
            'cmd',
            StatementType::Select->value,
            StatementType::Insert->value,
            StatementType::Update->value,
            StatementType::Delete->value,
            StatementType::CreateTable->value,
            StatementType::AlterTable->value,
            StatementType::DropTable->value,
        ];
    }

    /**
     * Generates one SQLite witness that satisfies the requested family constraints.
     */
    public function generateWitness(FamilyRequest $request): SqlWitness
    {
        $this->assertFamilyParameters($request);

        return match ($request->familyId) {
            'sqlite.statement.any' => $this->searchWitness($request->familyId, $request->parameters, fn (array $parameters, int $seed): string => $this->provider->generate(new GenerationRequest(seed: $seed, maxDepth: 8))),
            'sqlite.statement.select' => $this->searchWitness($request->familyId, $request->parameters, fn (array $parameters, int $seed): string => $this->provider->generate(new GenerationRequest(startRule: StatementType::Select->value, seed: $seed, maxDepth: 8))),
            'sqlite.statement.insert' => $this->searchWitness($request->familyId, $request->parameters, fn (array $parameters, int $seed): string => $this->provider->generate(new GenerationRequest(startRule: StatementType::Insert->value, seed: $seed, maxDepth: 8))),
            'sqlite.statement.update' => $this->searchWitness($request->familyId, $request->parameters, fn (array $parameters, int $seed): string => $this->provider->generate(new GenerationRequest(startRule: StatementType::Update->value, seed: $seed, maxDepth: 8))),
            'sqlite.statement.delete' => $this->searchWitness($request->familyId, $request->parameters, fn (array $parameters, int $seed): string => $this->provider->generate(new GenerationRequest(startRule: StatementType::Delete->value, seed: $seed, maxDepth: 8))),
            'sqlite.statement.create_table' => $this->searchWitness($request->familyId, $request->parameters, fn (array $parameters, int $seed): string => $this->provider->generate(new GenerationRequest(startRule: StatementType::CreateTable->value, seed: $seed, maxDepth: 8))),
            'sqlite.statement.alter_table' => $this->searchWitness($request->familyId, $request->parameters, fn (array $parameters, int $seed): string => $this->provider->generate(new GenerationRequest(startRule: StatementType::AlterTable->value, seed: $seed, maxDepth: 8))),
            'sqlite.statement.drop_table' => $this->searchWitness($request->familyId, $request->parameters, fn (array $parameters, int $seed): string => $this->provider->generate(new GenerationRequest(startRule: StatementType::DropTable->value, seed: $seed, maxDepth: 8))),
            'sqlite.constraint.select.star_requires_from' => $this->generateStarRequiresFromWitness($request),
            'sqlite.constraint.attach.expression' => $this->searchWitness($request->familyId, $request->parameters, fn (array $parameters, int $seed): string => $this->provider->generate(new GenerationRequest(startRule: 'attach_stmt', seed: $seed, maxDepth: 8))),
            'sqlite.constraint.detach.expression' => $this->searchWitness($request->familyId, $request->parameters, fn (array $parameters, int $seed): string => $this->provider->generate(new GenerationRequest(startRule: 'detach_stmt', seed: $seed, maxDepth: 8))),
            'sqlite.constraint.temporary_object_name_binding' => $this->generateTemporaryObjectNameBindingWitness($request),
            'sqlite.constraint.vacuum.into_expression' => $this->searchWitness($request->familyId, $request->parameters, fn (array $parameters, int $seed): string => $this->provider->generate(new GenerationRequest(startRule: 'vacuum_stmt', seed: $seed, maxDepth: 8))),
            'sqlite.constraint.select.set_operation' => $this->generateSetOperationWitness($request),
            'sqlite.constraint.select.values_clause' => $this->generateValuesClauseWitness($request),
            'sqlite.lex.identifier.context' => $this->searchWitness($request->familyId, $request->parameters, fn (array $parameters): string => 'SELECT ' . $this->provider->identifier(1)),
            default => throw new LogicException(sprintf('Unsupported family: %s', $request->familyId)),
        };
    }

    /**
     * @return list<FamilyDefinition>
     */
    protected function buildFamilies(): array
    {
        return [
            new FamilyDefinition('sqlite.statement.any', 'Any SQLite statement through the default provider entry.', 'spec', ['cmd']),
            new FamilyDefinition('sqlite.statement.select', 'SELECT statements through the provider surface.', 'spec', [StatementType::Select->value]),
            new FamilyDefinition('sqlite.constraint.select.star_requires_from', 'SELECT result-column family after star/from binding.', 'contract', [StatementType::Select->value, 'oneselect', 'safe_selcollist_no_from', 'safe_from_clause']),
            new FamilyDefinition('sqlite.constraint.select.set_operation', 'SELECT set-operation family after finite equal-arity operand refinement.', 'contract', ['selectnowith', 'setop_select_stmt', 'setop_select_stmt_1', 'setop_select_stmt_8'], ['arity'], ['left_projection_arity', 'right_projection_arity']),
            new FamilyDefinition('sqlite.constraint.select.values_clause', 'VALUES clause family after finite row-arity refinement.', 'contract', ['oneselect', 'select_values_clause', 'select_values_clause_1', 'select_values_clause_8'], ['arity'], ['row_arity']),
            new FamilyDefinition('sqlite.constraint.temporary_object_name_binding', 'TEMP TABLE, VIEW, and TRIGGER families after unqualified-name binding.', 'contract', ['create_table', 'create_view_stmt', 'create_trigger_stmt', 'trigger_decl']),
            new FamilyDefinition('sqlite.statement.insert', 'INSERT statements through the provider surface.', 'spec', ['cmd']),
            new FamilyDefinition('sqlite.statement.update', 'UPDATE statements through the provider surface.', 'spec', ['cmd']),
            new FamilyDefinition('sqlite.statement.delete', 'DELETE statements through the provider surface.', 'spec', ['cmd']),
            new FamilyDefinition('sqlite.statement.create_table', 'CREATE TABLE statements through the provider surface.', 'spec', [StatementType::CreateTable->value]),
            new FamilyDefinition('sqlite.statement.alter_table', 'ALTER TABLE statements through the provider surface.', 'spec', [StatementType::AlterTable->value]),
            new FamilyDefinition('sqlite.statement.drop_table', 'DROP TABLE statements through the provider surface.', 'spec', [StatementType::DropTable->value]),
            new FamilyDefinition('sqlite.constraint.attach.expression', 'ATTACH DATABASE statements after filename/schema expression restriction.', 'contract', ['attach_stmt', 'safe_attach_filename_expr', 'safe_attach_schema_expr']),
            new FamilyDefinition('sqlite.constraint.detach.expression', 'DETACH DATABASE statements after schema expression restriction.', 'contract', ['detach_stmt', 'safe_attach_schema_expr']),
            new FamilyDefinition('sqlite.constraint.vacuum.into_expression', 'VACUUM statements after INTO expression restriction.', 'contract', ['vacuum_stmt', 'safe_vinto', 'safe_vacuum_into_expr']),
            new FamilyDefinition('sqlite.lex.identifier.context', 'Identifier rendering inside a distinguishing SELECT context.', 'spec', ['nm']),
        ];
    }

    private function generateStarRequiresFromWitness(FamilyRequest $request): SqlWitness
    {
        return $this->searchWitness(
            $request->familyId,
            $request->parameters,
            fn (array $parameters, int $seed): string => $this->provider->generate(new GenerationRequest(startRule: 'oneselect', seed: $seed, maxDepth: 8)),
            static fn (string $sql, array $parameters): bool => str_starts_with($sql, 'SELECT '),
            null,
            128,
        );
    }

    private function generateTemporaryObjectNameBindingWitness(FamilyRequest $request): SqlWitness
    {
        $rules = ['create_table', 'create_view_stmt', 'create_trigger_stmt'];
        $ruleIndex = 0;

        return $this->searchWitness(
            $request->familyId,
            $request->parameters,
            function (array $parameters, int $seed) use (&$ruleIndex, $rules): string {
                $rule = $rules[$ruleIndex % count($rules)];
                $ruleIndex++;

                return $this->provider->generate(new GenerationRequest(startRule: $rule, seed: $seed, maxDepth: 8));
            },
            fn (string $sql, array $parameters): bool => $this->isTemporaryObjectNameBindingWitness($sql),
            null,
            512,
        );
    }

    private function generateSetOperationWitness(FamilyRequest $request): SqlWitness
    {
        $expectedArity = $this->requireArity($request);

        return $this->searchWitness(
            $request->familyId,
            ['arity' => $expectedArity],
            fn (array $parameters, int $seed): string => $this->provider->generate(new GenerationRequest(startRule: sprintf('setop_select_stmt_%d', (int) $parameters['arity']), seed: $seed, maxDepth: 8)),
            fn (string $sql, array $parameters): bool => $this->isSetOperationArityWitness($sql, (int) $parameters['arity']),
            fn (string $sql, array $parameters): array => $this->extractSetOperationProperties($sql),
            128,
        );
    }

    private function generateValuesClauseWitness(FamilyRequest $request): SqlWitness
    {
        $expectedArity = $this->requireArity($request);

        return $this->searchWitness(
            $request->familyId,
            ['arity' => $expectedArity],
            fn (array $parameters, int $seed): string => $this->provider->generate(new GenerationRequest(startRule: sprintf('select_values_clause_%d', (int) $parameters['arity']), seed: $seed, maxDepth: 8)),
            fn (string $sql, array $parameters): bool => $this->isValuesClauseArityWitness($sql, (int) $parameters['arity']),
            fn (string $sql, array $parameters): array => $this->extractValuesClauseProperties($sql),
            64,
        );
    }

    private function requireArity(FamilyRequest $request): int
    {
        $arity = $request->parameters['arity'] ?? null;
        if (!is_int($arity) && !is_string($arity)) {
            throw new LogicException(sprintf('arity parameter must be present for family %s.', $request->familyId));
        }

        $expectedArity = (int) $arity;
        if ($expectedArity < 1 || $expectedArity > 8) {
            throw new LogicException('arity parameter must be between 1 and 8.');
        }

        return $expectedArity;
    }

    private function isSetOperationArityWitness(string $sql, int $arity): bool
    {
        $properties = $this->extractSetOperationProperties($sql);

        return ($properties['left_projection_arity'] ?? null) === $arity
            && ($properties['right_projection_arity'] ?? null) === $arity;
    }

    private function isValuesClauseArityWitness(string $sql, int $arity): bool
    {
        $properties = $this->extractValuesClauseProperties($sql);

        return ($properties['row_arity'] ?? null) === $arity;
    }

    /**
     * @return array<string, scalar>
     */
    private function extractSetOperationProperties(string $sql): array
    {
        [$leftOperand, $rightOperand] = $this->splitTopLevelSetOperation($sql);
        if ($leftOperand === null || $rightOperand === null) {
            return [];
        }

        $leftArity = $this->setOperationOperandArity($leftOperand);
        $rightArity = $this->setOperationOperandArity($rightOperand);
        if ($leftArity === null || $rightArity === null) {
            return [];
        }

        return [
            'left_projection_arity' => $leftArity,
            'right_projection_arity' => $rightArity,
        ];
    }

    /**
     * @return array<string, scalar>
     */
    private function extractValuesClauseProperties(string $sql): array
    {
        if (!str_starts_with($sql, 'VALUES(')) {
            return [];
        }

        $depth = 0;
        $singleQuoted = false;
        $doubleQuoted = false;
        $length = strlen($sql);
        $start = 7;

        for ($i = $start; $i < $length; $i++) {
            $char = $sql[$i];

            if ($char === "'" && !$doubleQuoted) {
                $singleQuoted = !$singleQuoted;
                continue;
            }

            if ($char === '"' && !$singleQuoted) {
                $doubleQuoted = !$doubleQuoted;
                continue;
            }

            if ($singleQuoted || $doubleQuoted) {
                continue;
            }

            if ($char === '(') {
                $depth++;
                continue;
            }

            if ($char === ')') {
                if ($depth === 0) {
                    return [
                        'row_arity' => $this->topLevelCsvArity(substr($sql, $start, $i - $start)),
                    ];
                }

                $depth--;
            }
        }

        return [];
    }

    private function setOperationOperandArity(string $operand): ?int
    {
        [$leftOperand, $rightOperand] = $this->splitTopLevelSetOperation($operand);
        if ($leftOperand !== null && $rightOperand !== null) {
            $leftArity = $this->setOperationOperandArity($leftOperand);
            $rightArity = $this->setOperationOperandArity($rightOperand);

            if ($leftArity === null || $rightArity === null || $leftArity !== $rightArity) {
                return null;
            }

            return $leftArity;
        }

        if (str_starts_with($operand, 'VALUES(')) {
            $properties = $this->extractValuesClauseProperties($operand);

            return isset($properties['row_arity']) && is_int($properties['row_arity']) ? $properties['row_arity'] : null;
        }

        $projection = $this->selectProjectionSegment($operand);
        if ($projection === null) {
            return null;
        }

        return $this->topLevelCsvArity($projection);
    }

    /**
     * @return array{?string, ?string}
     */
    private function splitTopLevelSetOperation(string $sql): array
    {
        $depth = 0;
        $singleQuoted = false;
        $doubleQuoted = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($char === "'" && !$doubleQuoted) {
                $singleQuoted = !$singleQuoted;
                continue;
            }

            if ($char === '"' && !$singleQuoted) {
                $doubleQuoted = !$doubleQuoted;
                continue;
            }

            if ($singleQuoted || $doubleQuoted) {
                continue;
            }

            if ($char === '(') {
                $depth++;
                continue;
            }

            if ($char === ')') {
                $depth--;
                continue;
            }

            if ($depth !== 0) {
                continue;
            }

            foreach ([' UNION ALL ', ' UNION ', ' EXCEPT '] as $operator) {
                if (substr($sql, $i, strlen($operator)) === $operator) {
                    return [
                        trim(substr($sql, 0, $i)),
                        trim(substr($sql, $i + strlen($operator))),
                    ];
                }
            }
        }

        return [null, null];
    }

    private function selectProjectionSegment(string $sql): ?string
    {
        if (!str_starts_with($sql, 'SELECT ')) {
            return null;
        }

        $offset = 7;
        foreach (['DISTINCT ', 'ALL '] as $modifier) {
            if (substr($sql, $offset, strlen($modifier)) === $modifier) {
                $offset += strlen($modifier);
                break;
            }
        }

        $depth = 0;
        $singleQuoted = false;
        $doubleQuoted = false;
        $length = strlen($sql);
        $segmentStart = $offset;
        $delimiters = [' FROM ', ' WHERE ', ' GROUP BY ', ' HAVING ', ' WINDOW ', ' ORDER BY ', ' LIMIT '];

        for ($i = $segmentStart; $i < $length; $i++) {
            $char = $sql[$i];

            if ($char === "'" && !$doubleQuoted) {
                $singleQuoted = !$singleQuoted;
                continue;
            }

            if ($char === '"' && !$singleQuoted) {
                $doubleQuoted = !$doubleQuoted;
                continue;
            }

            if ($singleQuoted || $doubleQuoted) {
                continue;
            }

            if ($char === '(') {
                $depth++;
                continue;
            }

            if ($char === ')') {
                $depth--;
                continue;
            }

            if ($depth !== 0) {
                continue;
            }

            foreach ($delimiters as $delimiter) {
                if (substr($sql, $i, strlen($delimiter)) === $delimiter) {
                    return trim(substr($sql, $segmentStart, $i - $segmentStart));
                }
            }
        }

        return trim(substr($sql, $segmentStart));
    }

    private function isTemporaryObjectNameBindingWitness(string $sql): bool
    {
        if (preg_match(
            '/^CREATE\s+TEMP(?:ORARY)?\s+(?:TABLE|VIEW|TRIGGER)\s+(?:IF\s+NOT\s+EXISTS\s+)?([^\s(]+)/',
            $sql,
            $matches,
        ) !== 1) {
            return false;
        }

        return !str_contains($matches[1], '.');
    }

    private function topLevelCsvArity(string $segment): int
    {
        $depth = 0;
        $singleQuoted = false;
        $doubleQuoted = false;
        $arity = 1;
        $length = strlen($segment);

        for ($i = 0; $i < $length; $i++) {
            $char = $segment[$i];

            if ($char === "'" && !$doubleQuoted) {
                $singleQuoted = !$singleQuoted;
                continue;
            }

            if ($char === '"' && !$singleQuoted) {
                $doubleQuoted = !$doubleQuoted;
                continue;
            }

            if ($singleQuoted || $doubleQuoted) {
                continue;
            }

            if ($char === '(') {
                $depth++;
                continue;
            }

            if ($char === ')') {
                $depth--;
                continue;
            }

            if ($char === ',' && $depth === 0) {
                $arity++;
            }
        }

        return $arity;
    }
}
