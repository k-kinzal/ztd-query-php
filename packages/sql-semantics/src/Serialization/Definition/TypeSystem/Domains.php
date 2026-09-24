<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\TypeSystem;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\TypeSystem\Domain;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Domain as Statement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\TypeDeclaration;

/**
 * Writes CREATE DOMAIN and ALTER DOMAIN from their typed operands.
 * @visibility SqlSemantics
 */
final class Domains
{
    /**
     * Returns null for statements outside the domain family.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return match (true) {
            $statement instanceof Statement\CreateDomainStatement => self::create($statement),
            $statement instanceof Statement\SetDomainDefaultStatement => new Tree('alter-domain', [self::alter($statement->domain), Build::keyword('SET DEFAULT'), Expressions::write($statement->default)]),
            $statement instanceof Statement\DropDomainDefaultStatement => new Tree('alter-domain', [self::alter($statement->domain), Build::keyword('DROP DEFAULT')]),
            $statement instanceof Statement\AlterDomainNullabilityStatement => new Tree('alter-domain', [self::alter($statement->domain), Build::keyword($statement->notNull ? 'SET NOT NULL' : 'DROP NOT NULL')]),
            $statement instanceof Statement\AddDomainConstraintStatement => new Tree('alter-domain', [self::alter($statement->domain), Build::keyword('ADD'), self::constraint($statement->constraint), ...($statement->notValid ? [Build::keyword('NOT VALID')] : [])]),
            $statement instanceof Statement\DropDomainConstraintStatement => new Tree('alter-domain', [self::alter($statement->domain), Build::keyword('DROP CONSTRAINT' . ($statement->ifExists ? ' IF EXISTS' : '')), Build::identifier([$statement->constraint], Dialect::PostgreSql), Build::keyword($statement->behavior->value)]),
            $statement instanceof Statement\ValidateDomainConstraintStatement => new Tree('alter-domain', [self::alter($statement->domain), Build::keyword('VALIDATE CONSTRAINT'), Build::identifier([$statement->constraint], Dialect::PostgreSql)]),
            default => null,
        };
    }

    /**
     * Writes the base type, then the default, the collation, and the constraints in their declared order.
     */
    public static function create(Statement\CreateDomainStatement $statement): Tree
    {
        return new Tree('create-domain', [
            Build::keyword('CREATE DOMAIN'),
            Build::identifier($statement->name->parts, Dialect::PostgreSql),
            Build::keyword('AS'),
            TypeDeclaration::write($statement->baseType),
            ...($statement->default === null ? [] : [Build::keyword('DEFAULT'), Expressions::write($statement->default)]),
            ...($statement->collation === null ? [] : [Build::keyword('COLLATE'), Build::identifier($statement->collation->parts, Dialect::PostgreSql)]),
            ...array_map(self::constraint(...), $statement->constraints),
        ]);
    }

    /**
     * Writes an optional constraint name and the constraint body.
     * @throws InvalidStructure
     */
    public static function constraint(Domain\DomainConstraint $constraint): Tree
    {
        [$name, $body] = match (true) {
            $constraint instanceof Domain\DomainNotNull => [$constraint->name, Build::keyword('NOT NULL')],
            $constraint instanceof Domain\DomainNullable => [$constraint->name, Build::keyword('NULL')],
            $constraint instanceof Domain\DomainCheck => [$constraint->name, new Tree('check', [Build::keyword('CHECK'), Build::parentheses(Expressions::write($constraint->condition))])],
            default => throw new InvalidStructure('Unclassified domain constraint: ' . $constraint::class),
        };
        return new Tree('domain-constraint', [...($name === null ? [] : [Build::keyword('CONSTRAINT'), Build::identifier([$name], Dialect::PostgreSql)]), $body]);
    }

    /**
     * The command prefix naming the altered domain.
     */
    public static function alter(QualifiedName $domain): Tree
    {
        return new Tree('alter-domain-target', [Build::keyword('ALTER DOMAIN'), Build::identifier($domain->parts, Dialect::PostgreSql)]);
    }
}
