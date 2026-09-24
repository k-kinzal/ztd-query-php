<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Extensibility;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Literal;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\Extension as Statement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Serialization\Definition\Catalog\ObjectAddresses;

/**
 * Writes extension, procedural language, and access method definitions from their operands.
 * @visibility SqlSemantics
 */
final class Extensions
{
    /**
     * Returns null for statements outside these definition forms.
     * @throws InvalidStructure
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        $dialect = Dialect::PostgreSql;
        return match (true) {
            $statement instanceof Statement\CreateExtensionStatement => new Tree('create-extension', [
                Build::keyword('CREATE EXTENSION' . ($statement->ifNotExists ? ' IF NOT EXISTS' : '')),
                Build::identifier([$statement->name], $dialect),
                ...($statement->schema === null ? [] : [Build::keyword('SCHEMA'), Build::identifier([$statement->schema], $dialect)]),
                ...($statement->version === null ? [] : [Build::keyword('VERSION'), self::text($statement->version)]),
                ...($statement->cascade ? [Build::keyword('CASCADE')] : []),
            ]),
            $statement instanceof Statement\UpdateExtensionStatement => new Tree('update-extension', [Build::keyword('ALTER EXTENSION'), Build::identifier([$statement->name], $dialect), Build::keyword('UPDATE'), ...($statement->version === null ? [] : [Build::keyword('TO'), self::text($statement->version)])]),
            $statement instanceof Statement\AddExtensionMemberStatement => new Tree('add-extension-member', [Build::keyword('ALTER EXTENSION'), Build::identifier([$statement->extension], $dialect), Build::keyword('ADD'), ObjectAddresses::write($statement->object)]),
            $statement instanceof Statement\DropExtensionMemberStatement => new Tree('drop-extension-member', [Build::keyword('ALTER EXTENSION'), Build::identifier([$statement->extension], $dialect), Build::keyword('DROP'), ObjectAddresses::write($statement->object)]),
            $statement instanceof Statement\CreateLanguageStatement => new Tree('create-language', [
                Build::keyword('CREATE' . ($statement->orReplace ? ' OR REPLACE' : '') . ($statement->trusted ? ' TRUSTED' : '') . ' LANGUAGE'),
                Build::identifier([$statement->name], $dialect),
                Build::keyword('HANDLER'),
                self::name($statement->handler),
                ...($statement->inline === null ? [] : [Build::keyword('INLINE'), self::name($statement->inline)]),
                ...($statement->validator === null ? [] : [Build::keyword('VALIDATOR'), self::name($statement->validator)]),
            ]),
            $statement instanceof Statement\CreateAccessMethodStatement => new Tree('create-access-method', [Build::keyword('CREATE ACCESS METHOD'), Build::identifier([$statement->name], $dialect), Build::keyword('TYPE ' . $statement->type->value . ' HANDLER'), self::name($statement->handler)]),
            default => null,
        };
    }

    /**
     * Writes a function name as quoted identifier components.
     */
    public static function name(QualifiedName $name): Tree
    {
        return Build::identifier($name->parts, Dialect::PostgreSql);
    }

    /**
     * Encodes decoded text such as a version as one standard string constant.
     * @throws InvalidStructure
     */
    public static function text(string $value): Tree
    {
        return new Tree('literal', [new Atom('literal', Literal::encode($value, Dialect::PostgreSql)[0])]);
    }
}
