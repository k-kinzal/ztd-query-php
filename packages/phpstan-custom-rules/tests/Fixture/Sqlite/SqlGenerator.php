<?php

declare(strict_types=1);

namespace SqlFaker\Generation;

final class SqlGenerator
{
    public function generateForeignKeyConstraint(): string
    {
        $head = 'SELECT 1';
        $fixed = $head . ' FROM sqlite_master';
        $constraint = $fixed;

        return $constraint;
    }

    public function generateAccumulatedStatement(): string
    {
        $sql = $this->generateDerivedStatement();
        $sql .= ' SHOW';

        return $sql;
    }

    public function generateMixedBranchStatement(bool $derived): string
    {
        if ($derived) {
            $sql = $this->generateDerivedStatement();
        } else {
            $sql = 'SHOW TABLES';
        }

        return $sql;
    }

    public function generateCyclicStatement(): string
    {
        $first = $second;
        $second = $first;

        return $first;
    }
}
