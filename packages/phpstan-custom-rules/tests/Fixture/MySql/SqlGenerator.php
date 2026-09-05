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

    public function generateFromCollaborators(): string
    {
        $terminals = (new Derivation())->of($this->grammar, $this->plan);
        $names = (new ParserSemantics())->applied(array_map(
            static fn ($terminal) => $terminal->value,
            $terminals,
        ));

        return $this->lexicalGrammar->realize($names);
    }

    public function generateFromGenericDerivation(): string
    {
        return $this->lexicalGrammar->realize((new \SqlFaker\Grammar\Derivation())->of($this->plan));
    }

    public function generateFromSqliteDerivation(): string
    {
        return $this->lexicalGrammar->realize((new \SqlFaker\Sqlite\Derivation())->of($this->plan));
    }

    public function generateFromPostgreSqlSemantics(): string
    {
        $terminals = (new \SqlFaker\Grammar\Derivation())->of($this->plan);

        return $this->lexicalGrammar->realize((new \SqlFaker\PostgreSql\ParserSemantics())->applied($terminals));
    }

    public function generateFromUnrelatedDerivation(): string
    {
        return $this->lexicalGrammar->realize((new \Other\Derivation())->of($this->plan));
    }

    public function generateFromUnrelatedSemantics(): string
    {
        $terminals = (new Derivation())->of($this->plan);

        return $this->lexicalGrammar->realize((new \Other\ParserSemantics())->applied($terminals));
    }

    public function generateFromFixedTerminals(): string
    {
        return $this->lexicalGrammar->realize((new ParserSemantics())->applied(['COMMIT_SYM']));
    }

    public function generateFromOtherDerivationMethod(): string
    {
        return $this->lexicalGrammar->realize((new Derivation())->fixed($this->plan));
    }

    public function generateFromReassignedTerminals(): string
    {
        $terminals = (new Derivation())->of($this->plan);
        $terminals = ['COMMIT_SYM'];

        return $this->lexicalGrammar->realize((new ParserSemantics())->applied($terminals));
    }
}
