<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\View;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\SelectLowering;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\MySql\Rules\SessionDatabase;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Platform\MySql\Statement\Name\CurrentUser;
use SqlSemantics\Platform\MySql\Statement\Query\Select as ParsedSelect;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\View\CreateView as ParsedCreateView;
use SqlSemantics\Platform\MySql\Statement\View\ViewCheckOption as ParsedViewCheckOption;

/**
 * Resolves a persistent declaration into its checked native heading and body.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CreateViewLowering
{
    /**
     * Binds the external identity and the catalog-visible position names.
     * @throws InvalidConstruction When a definer cannot resolve to a known account form
     */
    public function lower(BoundStatement $operation, ParsedCreateView $statement): CreateView
    {
        $definition = $statement->definition;
        Check::input(!($statement->orReplace && $definition->ifNotExists), 'CREATE VIEW needs one admitted creation policy.');
        Check::input($definition->query instanceof ParsedSelect, 'A checked view requires one ordinary SELECT block.');
        Check::input($operation->context->relationNames === Comparison::Sensitive, 'CREATE VIEW requires the native case-sensitive relation profile.');
        $schema = ($definition->name->schema ?? $operation->context->declarationSchema)->value;
        Check::input($schema !== SessionDatabase::UNNAMED && $definition->name->catalog === null, 'CREATE VIEW requires an absolute view identity.');
        $name = new ViewName(new Identifier($schema), new Identifier($definition->name->name->value));
        $aliases = new ViewProjectionAliases();
        foreach ($definition->query->items as $item) {
            if ($item instanceof SelectExpression && $item->alias !== null) {
                $aliases->check($item->alias->value);
            }
        }
        $query = (new SelectLowering())->lower($operation, $definition->query);
        $columns = (new ViewHeadingLowering())->lower($operation, $definition, $query);
        $policy = match (true) {
            $statement->orReplace => ViewCreationPolicy::Replace,
            $definition->ifNotExists => ViewCreationPolicy::Preserve,
            default => ViewCreationPolicy::RequireNew,
        };

        $check = match ($definition->check) {
            null => ViewCheckOption::None,
            ParsedViewCheckOption::Local => ViewCheckOption::Local,
            ParsedViewCheckOption::Unqualified, ParsedViewCheckOption::Cascaded => ViewCheckOption::Cascaded,
        };

        $account = $definition->definer;
        if ($account instanceof AccountName && $account->host !== null
            && ($query->release === GrammarRelease::MySql5651 || $query->release === GrammarRelease::MySql5744)) {
            $maximum = $query->release === GrammarRelease::MySql5651 ? 16 : 32;
            Check::input(preg_match('/\A.{0,' . $maximum . '}\z/usD', $account->user->value) === 1, 'An explicit MySQL definer user exceeds the native character limit of this release.');
            Check::input(strlen($account->host->value) <= 60, 'A MySQL 5.6 or 5.7 view definer host permits at most 60 bytes.');
        }
        $definer = match (true) {
            $account === null, $account instanceof CurrentUser => null,
            $account instanceof AccountName => new ViewDefiner($account->user->value, $account->host?->value ?? '%'),
            default => throw new InvalidConstruction('A view definer must select the current user or a named account.'),
        };

        return new CreateView(
            $name,
            $query,
            $columns,
            $policy,
            ViewAlgorithm::from($definition->algorithm->value ?? 'UNDEFINED'),
            ViewSecurity::from($definition->security->value ?? 'DEFINER'),
            $check,
            $definer
        );
    }
}
