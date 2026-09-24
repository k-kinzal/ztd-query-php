<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\MySqlObject;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\MySqlNames;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Server\LoadableResult;
use SqlSemantics\Model\Statement\Definition\MySql\Server\CreateLoadableFunctionStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Binds CREATE [AGGREGATE] FUNCTION ... RETURNS ... SONAME, the loadable function registration.
 * @visibility SqlSemantics
 */
final class LoadableFunctions
{
    /**
     * Returns null when the statement declares a stored function instead.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function bind(Origin $origin, Node $source, Identifiers $identifiers): ?CreateLoadableFunctionStatement
    {
        $tail = Tree::outer($source, ['udf_tail'])[0] ?? null;
        if ($tail === null) {
            return null;
        }
        $name = $identifiers->name((Tree::child($tail, ['ident']) ?? throw new UnclassifiedSql('A loadable function requires its name.'))->tokens()[0]);
        if ($name === '') {
            throw new InvalidSql(InputViolation::ServerDefinition, $tail);
        }
        $type = Tree::child($tail, ['udf_type']) ?? throw new UnclassifiedSql('A loadable function requires its result kind.');
        $returns = match ($type->tokens()[0]->name ?? '') {
            'STRING_SYM' => LoadableResult::String,
            'REAL', 'REAL_SYM' => LoadableResult::Real,
            'DECIMAL_SYM' => LoadableResult::Decimal,
            default => LoadableResult::Integer,
        };
        $library = MySqlNames::read((Tree::child($tail, ['TEXT_STRING_sys']) ?? throw new UnclassifiedSql('A loadable function requires its library.'))->tokens()[0], $identifiers);
        return new CreateLoadableFunctionStatement($origin, $name, $returns, $library, strtoupper($tail->tokens()[0]->text ?? '') === 'AGGREGATE', Tree::child($tail, ['opt_if_not_exists']) !== null);
    }
}
