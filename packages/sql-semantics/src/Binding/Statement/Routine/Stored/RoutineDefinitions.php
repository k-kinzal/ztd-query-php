<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Routine\Stored;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\MySqlNames;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Routine\Program\Domains;
use SqlSemantics\Binding\Statement\Routine\Program\ProgramBinder;
use SqlSemantics\Binding\Statement\Routine\Program\ProgramFrame;
use SqlSemantics\Binding\Statement\Routine\Program\ProgramNamespace;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\LocalVariable;
use SqlSemantics\Model\Definition\Routine\Body\ProgramStatement;
use SqlSemantics\Model\Definition\Routine\ParameterMode;
use SqlSemantics\Model\Definition\Routine\Stored\ExternalRoutineCode;
use SqlSemantics\Model\Definition\Routine\Stored\FunctionParameter;
use SqlSemantics\Model\Definition\Routine\Stored\ProcedureParameter;
use SqlSemantics\Model\Definition\Routine\Stored\ProgramKind;
use SqlSemantics\Model\Definition\Routine\Stored\ProgramStructure;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateFunctionStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds CREATE PROCEDURE and CREATE FUNCTION with their parameters, characteristics and SQL or external body.
 * @visibility SqlSemantics
 */
final class RoutineDefinitions
{
    /**
     * Parameters are local variables of the body; a function body requires RETURN.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     * @throws InvalidStructure
     */
    public static function bind(Origin $origin, Node $tail, QueryContext $context, AccountName|CurrentAccount|null $definer): CreateProcedureStatement|CreateFunctionStatement
    {
        $function = $tail->name === 'sf_tail';
        $identifiers = $context->tables->identifiers;
        $name = StoredPrograms::name(Tree::child($tail, ['sp_name']), $context);
        $parameters = self::parameters($tail, $identifiers);
        [$characteristics, $language] = Characteristics::read(Tree::child($tail, ['sp_c_chistics']), $context);
        $kind = $function ? ProgramKind::Function : ProgramKind::Procedure;
        $bodyNode = Tree::child($tail, ['stored_routine_body', 'sp_proc_stmt']) ?? throw new UnclassifiedSql('A routine requires its body.');
        $variables = array_map(static fn (ProcedureParameter|FunctionParameter $parameter): LocalVariable => new LocalVariable($parameter->name, $parameter->domain), $parameters);
        $procedureParameters = array_values(array_filter($parameters, static fn (ProcedureParameter|FunctionParameter $parameter): bool => $parameter instanceof ProcedureParameter));
        $functionParameters = array_values(array_filter($parameters, static fn (ProcedureParameter|FunctionParameter $parameter): bool => $parameter instanceof FunctionParameter));
        $body = self::body($bodyNode, $language, ProgramFrame::start($kind, $context, (new ProgramNamespace())->declare($variables)), $identifiers);
        if ($function) {
            return new CreateFunctionStatement($origin, $name, $functionParameters, Domains::read($tail, $identifiers), $characteristics, $body, $definer, Tree::child($tail, ['opt_if_not_exists']) !== null);
        }
        return new CreateProcedureStatement($origin, $name, $procedureParameters, $characteristics, $body, $definer, Tree::child($tail, ['opt_if_not_exists']) !== null);
    }

    /**
     * Reads the parameters in order; a name repeated ignoring case is diagnosed.
     * @return list<ProcedureParameter|FunctionParameter>
     * @throws InvalidSql
     * @throws UnclassifiedSql
     * @throws InvalidStructure
     */
    public static function parameters(Node $tail, \SqlSemantics\Ast\Identifiers $identifiers): array
    {
        $parameters = [];
        foreach (Tree::outer(Tree::child($tail, ['sp_pdparam_list', 'sp_fdparam_list']) ?? new Node('parameters', 0, []), ['sp_pdparam', 'sp_fdparam']) as $parameter) {
            $label = $identifiers->name((Tree::child($parameter, ['ident']) ?? throw new UnclassifiedSql('A parameter requires its name.'))->tokens()[0]);
            if (array_filter($parameters, static fn (ProcedureParameter|FunctionParameter $known): bool => strcasecmp($known->name, $label) === 0) !== []) {
                throw new InvalidSql(InputViolation::ProgramDeclaration, $parameter);
            }
            $mode = strtoupper(Tree::child($parameter, ['sp_opt_inout'])?->tokens()[0]->text ?? 'IN');
            $parameters[] = $tail->name === 'sf_tail' ? new FunctionParameter($label, Domains::read($parameter, $identifiers)) : new ProcedureParameter($label, Domains::read($parameter, $identifiers), ParameterMode::from($mode));
        }
        return $parameters;
    }

    /**
     * A LANGUAGE SQL routine has a compound-statement body, and a routine in another language a string body.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function body(Node $node, string $language, ProgramFrame $frame, \SqlSemantics\Ast\Identifiers $identifiers): ProgramStatement|ExternalRoutineCode
    {
        $sql = strcasecmp($language, 'SQL') === 0;
        $string = Tree::child($node, ['routine_string']);
        if ($string !== null) {
            if ($sql) {
                throw new InvalidSql(InputViolation::ProgramDefinition, $node);
            }
            return new ExternalRoutineCode($language, self::code($string->tokens()[0] ?? throw new UnclassifiedSql('A routine string requires its text.'), $identifiers));
        }
        if (!$sql) {
            throw new InvalidSql(InputViolation::ProgramDefinition, $node);
        }
        $body = ProgramBinder::statement($node, $frame);
        try {
            ProgramStructure::check($body, $frame->kind);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::ProgramStatement, $node, $error);
        }
        return $body;
    }

    /**
     * Decodes a quoted or dollar-quoted routine string into the source text it contains.
     */
    public static function code(Token $token, \SqlSemantics\Ast\Identifiers $identifiers): string
    {
        if (preg_match('/^\$([A-Za-z0-9_]*)\$(.*)\$\1\$$/Ds', $token->text, $match) === 1) {
            return $match[2];
        }
        return MySqlNames::read($token, $identifiers);
    }
}
