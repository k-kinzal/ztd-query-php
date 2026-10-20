<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit;

use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup as Resource;
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
        if ($code === 1477 && ($node instanceof AlterServer || $node instanceof DropServer)) {
            return [new CreateServer($node->name, new Name('mysql'), [new ServerOption(ServerOptionKind::Host, new Text('localhost'))])];
        }
        if ($code === 3651 && ($node instanceof Resource\AlterResourceGroup || $node instanceof Resource\DropResourceGroup || $node instanceof Resource\SetResourceGroup)) {
            return [new Resource\CreateResourceGroup($node->name, Resource\ResourceGroupKind::User)];
        }
        return (new HandlerHistory())->steps($node, $code);
    }
}
