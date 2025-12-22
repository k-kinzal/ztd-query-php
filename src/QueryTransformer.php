<?php

declare(strict_types=1);

namespace ZtdQuery;

/**
 * Contract for legacy SQL transformation interfaces.
 */
interface QueryTransformer
{
    /**
     * Transform SQL using provided shadow data.
     *
     * @param array<string, array<int, array<string, mixed>>> $tableData
     */
    public function transform(string $sql, array $tableData): string;
}

class_alias(QueryTransformer::class, 'KKinzal\\ZtdQueryPhp\\QueryTransformerInterface');
