<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit;

use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup as Resource;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\AlterServer;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\CreateServer;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\DropServer;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\ServerOption;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\ServerOptionKind;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;

/**
 * Supplies catalog fixtures using the original structure's referenced identities.
 *
 * Setup must itself succeed against both engines. The original object is then
 * repeated unchanged; a different later rejection still needs its own explanation.
 */
final class CatalogHistory
{
    /**
     * @return list<Statement> Explicit setup before repeating the original object
     */
    public function steps(Statement|Scalar $node, int $code): array
    {
        if ($code === 1539 && ($node instanceof M\Routine\AlterEvent || $node instanceof M\Utility\Show\Program\ShowCreateEvent)) {
            return [new M\Routine\CreateEvent($node->name, new M\Routine\Event\OnceSchedule(new M\Literal\StringLiteral(['2030-01-01 00:00:00'])), new M\Dml\Evaluation([new M\Query\SelectExpression(new M\Literal\NumberLiteral('7'))]), M\Routine\Event\Completion::Preserve, M\Routine\Event\EventStatus::Disable)];
        }
        if ($code === 1347 && ($node instanceof M\Utility\Show\Schema\ShowCreateView || $node instanceof M\View\AlterView)) {
            $name = $node instanceof M\View\AlterView ? $node->definition->name : $node->view->name;
            return [new M\Alter\DropTable(false, false, [new M\Alter\TargetTable($name)]), new M\View\CreateView(new M\View\ViewDefinition($name, new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('7'), new Name('fixture_value'))])))];
        }
        if ($code === 1477 && ($node instanceof AlterServer || $node instanceof DropServer)) {
            return [new CreateServer($node->name, new Name('mysql'), [new ServerOption(ServerOptionKind::Host, new Text('localhost'))])];
        }
        if ($code === 3651 && ($node instanceof Resource\AlterResourceGroup || $node instanceof Resource\DropResourceGroup || $node instanceof Resource\SetResourceGroup)) {
            return [new Resource\CreateResourceGroup($node->name, Resource\ResourceGroupKind::User)];
        }
        return [...(new RoleHistory())->steps($node, $code), ...(new HandlerHistory())->steps($node, $code), ...(new Table\Changes())->steps($node, $code)];
    }
}
