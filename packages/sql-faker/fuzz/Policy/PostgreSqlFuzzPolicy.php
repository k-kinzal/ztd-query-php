<?php

declare(strict_types=1);

namespace Fuzz\Policy;

use Fuzz\Probe\ProbeResult;

/**
 * Interprets PostgreSQL SQLSTATEs for fuzzing and separates expected semantic
 * rejections from parser failures or uncatalogued engine responses.
 */
final class PostgreSqlFuzzPolicy implements FuzzPolicy
{
    /**
     * Returns the SQL dialect handled by this policy.
     */
    public function dialect(): string
    {
        return 'postgresql';
    }

    /**
     * Classifies the probe result into an ignorable outcome or a crash-worthy
     * contract violation based on SQLSTATE and selected message refinements.
     */
    public function classify(ProbeResult $probeResult): FuzzDecision
    {
        if ($probeResult->accepted) {
            return FuzzDecision::ignore('accepted', 'statement executed successfully');
        }

        $sqlState = $probeResult->sqlState;
        $message = $probeResult->message ?? '';
        if ($sqlState === null) {
            return FuzzDecision::crash('unknown', 'PostgreSQL did not provide SQLSTATE');
        }

        if ($sqlState === '22023'
            && str_contains($message, 'role "')
            && str_contains($message, 'does not exist')) {
            return FuzzDecision::ignore('state', 'invalid_parameter_value: role does not exist');
        }

        if ($sqlState === '22023'
            && (str_contains($message, 'no security label providers have been loaded')
                || (str_contains($message, 'security label provider') && str_contains($message, 'not loaded')))) {
            return FuzzDecision::ignore('environment', 'invalid_parameter_value: security label provider is not loaded');
        }

        if ($sqlState === '42P16'
            && (str_contains($message, 'cannot drop columns from view')
                || str_contains($message, 'cannot change name of view column'))) {
            return FuzzDecision::ignore('state', 'invalid_table_definition: view column shape is immutable');
        }

        if ($sqlState === '42P10'
            && str_contains($message, 'there is no unique or exclusion constraint matching the ON CONFLICT specification')) {
            return FuzzDecision::ignore('state', 'invalid_column_reference: ON CONFLICT target does not match a unique or exclusion constraint');
        }

        if ($sqlState === '42P17'
            && str_contains($message, 'is not partitioned')) {
            return FuzzDecision::ignore('state', 'invalid_object_definition: relation is not partitioned');
        }

        if ($sqlState === '57014'
            && str_contains($message, 'statement timeout')) {
            return FuzzDecision::ignore('resource', 'query_canceled: statement timeout');
        }

        $stateReason = $this->stateReasonForSqlState($sqlState);
        if ($stateReason !== null) {
            return FuzzDecision::ignore('state', $stateReason);
        }

        $environmentReason = $this->environmentReasonForSqlState($sqlState);
        if ($environmentReason !== null) {
            return FuzzDecision::ignore('environment', $environmentReason);
        }

        $resourceReason = $this->resourceReasonForSqlState($sqlState);
        if ($resourceReason !== null) {
            return FuzzDecision::ignore('resource', $resourceReason);
        }

        $syntaxReason = $this->syntaxReasonForSqlState($sqlState);
        if ($syntaxReason !== null) {
            return FuzzDecision::crash('syntax', $syntaxReason);
        }

        return FuzzDecision::crash('contract', sprintf('Unhandled PostgreSQL SQLSTATE %s', $sqlState));
    }

    private function stateReasonForSqlState(string $sqlState): ?string
    {
        return match ($sqlState) {
            '25001' => 'active_sql_transaction',
            '25P01' => 'no_active_sql_transaction',
            '26000' => 'invalid_sql_statement_name',
            '2BP01' => 'dependent_objects_still_exist',
            '34000' => 'invalid_cursor_name',
            '3B001' => 'invalid_savepoint_specification',
            '3D000' => 'invalid_catalog_name',
            '3F000' => 'invalid_schema_name',
            '42703' => 'undefined_column',
            '42704' => 'undefined_object',
            '42710' => 'duplicate_object',
            '42809' => 'wrong_object_type',
            '42883' => 'undefined_function',
            '42P01' => 'undefined_table',
            '42P03' => 'duplicate_cursor',
            '42P06' => 'duplicate_schema',
            '42P07' => 'duplicate_table',
            '55000' => 'object_not_in_prerequisite_state',
            '0LP01' => 'invalid_grant_operation',
            default => null,
        };
    }

    private function environmentReasonForSqlState(string $sqlState): ?string
    {
        return match ($sqlState) {
            '0A000' => 'feature_not_supported',
            '58P01' => 'undefined_file',
            default => null,
        };
    }

    private function resourceReasonForSqlState(string $sqlState): ?string
    {
        return match ($sqlState) {
            '53200' => 'out_of_memory',
            default => null,
        };
    }

    private function syntaxReasonForSqlState(string $sqlState): ?string
    {
        return match ($sqlState) {
            '42601' => 'syntax_error',
            default => null,
        };
    }
}
