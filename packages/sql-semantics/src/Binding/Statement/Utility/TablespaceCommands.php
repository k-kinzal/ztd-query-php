<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Utility;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Configuration\Role\PostgreSqlRoles;
use SqlSemantics\Binding\Configuration\SettingTokens;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scalar\Intrinsic\FieldSpelling;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Database\PostgreSql\TablespaceInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Tablespace as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Storage\Parameter;

/**
 * Binds PostgreSQL CREATE, ALTER and DROP TABLESPACE without touching the server file system.
 * @visibility SqlSemantics
 */
final class TablespaceCommands
{
    /**
     * Returns the tablespace operation of a CreateTableSpaceStmt, AlterTblSpcStmt or DropTableSpaceStmt.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): BoundStatement
    {
        $identifiers = $context->tables->identifiers;
        $name = $identifiers->name((Tree::child($source, ['name']) ?? throw new UnclassifiedSql('A tablespace operation requires its name.'))->tokens()[0]);
        $words = SettingTokens::words($source->tokens());
        $scope = new Scope($identifiers, queries: $context);
        try {
            return match ($source->name) {
                'DropTableSpaceStmt' => new Statement\DropTablespaceStatement($origin, $name, ($words[2] ?? '') === 'IF'),
                'CreateTableSpaceStmt' => self::create($origin, $source, $name, $scope),
                default => in_array('RESET', $words, true)
                    ? new Statement\ResetTablespaceOptionsStatement($origin, $name, Collections::nonEmpty(self::names($source, $scope)))
                    : new Statement\SetTablespaceOptionsStatement($origin, $name, Collections::nonEmpty(self::parameters($source, $scope))),
            };
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::TablespaceOption, $source, $error);
        }
    }

    /**
     * Reads the owner, the absolute server directory and the parameter overrides.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     * @throws InvalidStructure
     */
    public static function create(Origin $origin, Node $source, string $name, Scope $scope): Statement\CreateTablespaceStatement
    {
        $owner = Tree::child($source, ['OptTableSpaceOwner']);
        $location = Tree::child($source, ['Sconst']) ?? throw new UnclassifiedSql('CREATE TABLESPACE requires its location.');
        $path = FieldSpelling::read($location->tokens()[0], $scope->identifiers);
        if (str_contains($path, "'") || ($path !== '' && preg_match('/^(?:[\/\\\\]|[A-Za-z]:)/', $path) !== 1)) {
            throw new InvalidSql(InputViolation::TablespaceOption, $location);
        }
        $literal = (new LiteralBinder(Dialect::PostgreSql))->bind($location->tokens()[0]);
        if (!$literal instanceof Literal) {
            throw new UnclassifiedSql('Unclassified tablespace location: ' . Tree::text($location));
        }
        $role = $owner === null ? null : PostgreSqlRoles::read(Tree::child($owner, ['RoleSpec']) ?? throw new UnclassifiedSql('OWNER requires its role.'));
        return new Statement\CreateTablespaceStatement($origin, $name, $role, $literal, self::parameters($source, $scope));
    }

    /**
     * Reads assigned parameters, diagnosing unknown names and values that are not constants.
     * @return list<Parameter>
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function parameters(Node $source, Scope $scope): array
    {
        $result = [];
        foreach (Tree::outer($source, ['reloption_elem']) as $element) {
            $tokens = $element->tokens();
            $value = array_slice($tokens, 2);
            $numeric = in_array($value[count($value) - 1]->name ?? '', ['ICONST', 'FCONST'], true);
            if (count($tokens) < 3 || $tokens[1]->text !== '=' || !in_array($scope->identifiers->name($tokens[0]), TablespaceInvariant::PARAMETERS, true)
                || !((count($value) === 1 && in_array($value[0]->name, ['ICONST', 'FCONST', 'SCONST'], true)) || (count($value) === 2 && $numeric && $value[0]->text === '+'))) {
                throw new InvalidSql(InputViolation::TablespaceOption, $element);
            }
            $literal = SettingTokens::value($value, $element, $scope);
            $result[] = new Parameter(new QualifiedName([$scope->identifiers->name($tokens[0])]), $literal instanceof Literal ? $literal : throw new InvalidSql(InputViolation::TablespaceOption, $element));
        }
        return $result;
    }

    /**
     * Reads removed parameter names; RESET cannot carry values.
     * @return list<QualifiedName>
     * @throws InvalidSql
     */
    public static function names(Node $source, Scope $scope): array
    {
        $result = [];
        foreach (Tree::outer($source, ['reloption_elem']) as $element) {
            if (in_array('=', array_map(static fn ($token): string => $token->text, $element->tokens()), true)) {
                throw new InvalidSql(InputViolation::ResetParameterValue, $element);
            }
            $result[] = new QualifiedName($scope->identifiers->parts($element));
        }
        return $result;
    }
}
