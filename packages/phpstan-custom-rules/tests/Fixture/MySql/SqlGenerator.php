<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

final class SqlGenerator
{
    public function statement(): string
    {
        return 'SELECT id FROM users';
    }

    public function generateUnknownStatement(): string
    {
        return 'SHOW TABLES';
    }

    public function generateDynamicUnknownStatement(string $table): string
    {
        return 'SHOW ' . $table;
    }

    public function generateDelegatedStatement(): string
    {
        return $this->generateDerivedStatement();
    }

    public function generateLexicalStatement(): string
    {
        $terminals = $this->derive();

        return $this->lexicalGrammar->realize($terminals);
    }

    public function generateMatchedStatement(bool $select): string
    {
        return match ($select) {
            true => $this->generateDelegatedStatement(),
            false => $this->generateDerivedStatement(),
        };
    }

    public function generateForeignKeyConstraint(): string
    {
        $constraint = $this->generateDerivedStatement();

        return $constraint;
    }

    public function generateLiteralRealization(): string
    {
        return $this->lexicalGrammar->realize(['COMMIT_SYM']);
    }

    public function generateTargetedStatement(int $targetDepth): string
    {
        return $this->generateRequiredWithPlan(
            'SelectStmt',
            $targetDepth,
            new \SqlFaker\Grammar\DerivationPlan([]),
            new \SqlFaker\Grammar\LexemePlan(['Op' => ['@@']]),
        );
    }

    public function generateLexicalIdentifier(): string
    {
        return $this->lexicalGrammar->generateQuotedIdentifier(1, 64);
    }
}
