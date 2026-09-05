<?php

declare(strict_types=1);

namespace SqlFaker;

final class MySqlProvider
{
    private \SqlFaker\Generation\SqlGenerator $sql;

    public function quotedIdentifier(): string
    {
        return $this->sql->generate(\SqlFaker\MySql\GenerationPlans::quotedIdentifier(1, 64));
    }

    public function statement(?string $type): string
    {
        return match ($type) {
            null => $this->sql->generate(\SqlFaker\Grammar\GenerationPlan::all()),
            default => $this->sql->generate(\SqlFaker\Grammar\GenerationPlan::fromRule('select_stmt')),
        };
    }

    public function keyword(): string
    {
        return $this->sql->generate('CASCADE');
    }

    public function byTarget(MySqlGenerationTarget $target): string
    {
        return match ($target) {
            default => $this->sql->generate(\SqlFaker\Grammar\GenerationPlan::fromRule('select_stmt')),
        };
    }
}

final class PostgreSqlProvider
{
    private \SqlFaker\Generation\SqlGenerator $sql;

    public function statement(): string
    {
        return $this->generateRequired('SelectStmt');
    }

    public function genericStatement(): string
    {
        return $this->sql->generate('SelectStmt');
    }
}

final class SqliteProvider
{
    private \SqlFaker\Generation\SqlGenerator $sql;

    public function statement(): string
    {
        $sql = $this->sql->generate('cmd');

        return $sql;
    }
}

final class TemplateProvider
{
    private \SqlFaker\MySql\TemplateBank $sql;

    public function statement(): string
    {
        return $this->sql->generateSelectStatement();
    }
}

final class AnonymousProviderFactory
{
    public function __construct()
    {
        new class () {
            public function statement(): string
            {
                return 'SELECT id FROM users';
            }
        };
    }
}

final class PromotedTemplateProvider
{
    public function __construct(private \SqlFaker\MySql\TemplateBank $sql)
    {
    }

    public function statement(): string
    {
        return $this->sql->generateSelectStatement();
    }
}

final class SuffixOnlyProvider
{
    private \SqlFaker\Fixed\SqlGenerator $sql;

    public function statement(): string
    {
        return $this->sql->generateStatement();
    }
}
