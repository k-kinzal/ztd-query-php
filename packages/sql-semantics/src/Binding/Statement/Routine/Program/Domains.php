<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Routine\Program;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\MySqlNames;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Ast\TypeReader;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Stored\DeclaredDomain;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Reads the declared type and optional collation of a parameter, return value or local variable.
 * @visibility SqlSemantics
 */
final class Domains
{
    /**
     * Before MySQL 8.0 a COLLATE clause requires a character set in the type; COLLATE DEFAULT declares no collation.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function read(Node $owner, Identifiers $identifiers): DeclaredDomain
    {
        $legacy = Tree::child($owner, ['type_with_opt_collate']);
        $holder = $legacy ?? $owner;
        $type = Tree::child($holder, ['type']) ?? throw new UnclassifiedSql('A stored program declaration requires its type.');
        $collate = Tree::child($holder, ['opt_collate']);
        $name = $collate === null ? null : (Tree::outer($collate, ['collation_name'])[0] ?? null);
        if ($legacy !== null && $collate !== null && !self::charset($type)) {
            throw new InvalidSql(InputViolation::ProgramDeclaration, $collate);
        }
        $token = $name?->tokens()[0] ?? null;
        $collation = $token === null ? null : ($token->name === 'BINARY' || $token->name === 'BINARY_SYM' ? 'binary' : MySqlNames::read($token, $identifiers));
        return new DeclaredDomain((new TypeReader(Dialect::MySql))->read($type), $collation);
    }

    /**
     * Reports whether a type names a character set, which a legacy COLLATE clause refines.
     */
    public static function charset(Node $type): bool
    {
        $words = array_map(static fn ($token): string => strtoupper($token->text), $type->tokens());
        return array_intersect($words, ['CHARSET', 'ASCII', 'UNICODE', 'BYTE', 'NATIONAL', 'NCHAR', 'NVARCHAR']) !== [] || str_contains(implode(' ', $words), 'CHARACTER SET');
    }
}
