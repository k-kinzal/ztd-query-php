<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Procedural;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\MySqlNames;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Condition\ConditionDiagnostic;
use SqlSemantics\Model\Configuration\Condition\ConditionItem;
use SqlSemantics\Model\Configuration\Condition\DiagnosticsArea;
use SqlSemantics\Model\Configuration\Condition\StatementDiagnostic;
use SqlSemantics\Model\Configuration\Condition\StatementItem;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Procedural\GetConditionDiagnosticsStatement;
use SqlSemantics\Model\Statement\Procedural\GetDiagnosticsStatement;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Binds GET DIAGNOSTICS outside stored programs, where every target is a user variable.
 * @visibility SqlSemantics
 */
final class Diagnostics
{
    /**
     * Separates statement information from the information of one numbered condition.
     * @throws InvalidSql
     */
    public static function bind(Origin $origin, Node $node, QueryContext $context): BoundStatement
    {
        $areaNode = Tree::child($node, ['which_area']);
        $area = $areaNode === null || Tree::text($areaNode) === '' ? DiagnosticsArea::Current : DiagnosticsArea::from(strtoupper(Tree::text($areaNode)));
        $number = Tree::outer($node, ['condition_number'])[0] ?? null;
        if ($number === null) {
            $items = array_map(static fn (Node $item): StatementDiagnostic => new StatementDiagnostic(self::target($item, $context), StatementItem::from(self::item($item))), Tree::outer($node, ['statement_information_item']));
            return new GetDiagnosticsStatement($origin, $area, $items);
        }
        $items = array_map(static fn (Node $item): ConditionDiagnostic => new ConditionDiagnostic(self::target($item, $context), ConditionItem::from(self::item($item))), Tree::outer($node, ['condition_information_item']));
        return new GetConditionDiagnosticsStatement($origin, $area, Conditions::value($number, $context), $items);
    }

    /**
     * Reads the user variable receiving an item; a bare name refers to a local variable no top-level statement declares.
     * @throws InvalidSql
     */
    public static function target(Node $item, QueryContext $context): string
    {
        $target = Tree::child($item, ['simple_target_specification']) ?? Tree::invalid($item, 'diagnostics target');
        $tokens = $target->tokens();
        if (($tokens[0]->text ?? '') !== '@' || !isset($tokens[1])) {
            throw new InvalidSql(InputViolation::ProgramReference, $target);
        }
        return MySqlNames::read($tokens[1], $context->tables->identifiers);
    }

    /**
     * Returns the keyword of the requested information item.
     */
    public static function item(Node $item): string
    {
        $tokens = $item->tokens();
        return strtoupper($tokens[count($tokens) - 1]->text ?? '');
    }
}
