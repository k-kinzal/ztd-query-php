<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Replication;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Replication\Publication\PublicationObjectChange;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Replication as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds CREATE PUBLICATION and the ALTER PUBLICATION option and object changes.
 * @visibility SqlSemantics
 */
final class Publications
{
    /**
     * Separates empty, all-table, and listed publications, and option from object alterations.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): BoundStatement
    {
        $name = $context->tables->identifiers->name((Tree::child($source, ['name']) ?? throw new UnclassifiedSql('A publication requires its name.'))->tokens()[0]);
        $list = Tree::child($source, ['pub_obj_list']);
        $objects = $list === null ? [] : PublicationMembers::read($list, $origin, $context);
        $definition = Tree::outer($source, ['definition'])[0] ?? null;
        $options = PublicationOptionReader::read($definition, $context);
        $words = array_map(static fn ($token): string => strtoupper($token->text), array_slice($source->tokens(), 0, 6));
        try {
            if ($source->name === 'AlterPublicationStmt') {
                return $list === null
                    ? new Statement\AlterPublicationOptionsStatement($origin, $name, $options)
                    : new Statement\AlterPublicationObjectsStatement($origin, $name, PublicationObjectChange::from($words[3] ?? ''), $objects);
            }
            if ($list !== null) {
                return new Statement\CreateObjectsPublicationStatement($origin, $name, $objects, $options);
            }
            return ($words[4] ?? '') === 'ALL'
                ? new Statement\CreateAllTablesPublicationStatement($origin, $name, $options)
                : new Statement\CreatePublicationStatement($origin, $name, $options);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::PublicationObject, $source, $error);
        }
    }
}
