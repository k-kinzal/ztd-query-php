<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Catalog;

/**
 * What a finding says about a catalogued statement.
 *
 * @visibility root
 */
enum FindingRule: string
{
    case UnresolvedSql = 'unresolved-sql';
    case DynamicSql = 'dynamic-sql';
    case ExternalInput = 'external-input';
    case PlaceholderCountMismatch = 'placeholder-count-mismatch';
    case AnalysisIncomplete = 'analysis-incomplete';
    case CallNotAnalyzed = 'call-not-analyzed';

    /**
     * How much attention findings of this rule deserve by default.
     */
    public function severity(): Severity
    {
        return match ($this) {
            self::ExternalInput => Severity::High,
            self::DynamicSql => Severity::Medium,
            self::PlaceholderCountMismatch => Severity::Medium,
            self::UnresolvedSql => Severity::Low,
            self::AnalysisIncomplete => Severity::Low,
            self::CallNotAnalyzed => Severity::Low,
        };
    }

    /**
     * A one-line explanation of what the rule reports.
     */
    public function describe(): string
    {
        return match ($this) {
            self::UnresolvedSql => 'The statement text could not be reconstructed at all.',
            self::DynamicSql => 'A value is spliced into the statement text instead of being bound.',
            self::ExternalInput => 'A value spliced into the statement text comes from external input.',
            self::PlaceholderCountMismatch => 'The statement binds a different number of values than it has placeholders.',
            self::AnalysisIncomplete => 'A cycle or an analysis budget stopped the search before it closed.',
            self::CallNotAnalyzed => 'A call that carries a statement was found but never examined.',
        };
    }
}
