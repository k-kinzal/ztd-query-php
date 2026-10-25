<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement;

use JsonException;
use LogicException;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operation as Model;
use Tests\Generator\Bindings;
use Tests\Generator\Catalogue;
use Tests\Generator\Choices;
use Tests\Generator\Construction;
use Tests\Generator\Inhabitant;
use Tests\Generator\Metadata;
use Tests\Generator\Profiles;

/**
 * An operation's context must use a language profile that admits its statement.
 */
final class OperationGenerator implements Inhabitant
{
    /**
     * Derives an operation from a generated original statement in a compatible context.
     *
     * @throws JsonException When the semantic value inventory cannot be read
     * @throws LogicException When the constructor sampler violates its requested type
     */
    public function sample(Choices $choices): Model
    {
        $statement = (new Construction($choices, new Catalogue(), new Metadata()))->object(Statement::class, 6);
        if (!$statement instanceof Statement) {
            throw new LogicException('The operation generator requires a MySQL statement.');
        }
        return $this->from($statement, $choices);
    }

    /**
     * Publishes the generated structure once, with a closed empty schema and bound NULL parameters.
     * The caller creates that same empty schema in MemoryDB and submits this operation unchanged.
     */
    public function from(Statement $statement, Choices $choices): Model
    {
        $profile = (new Profiles())->select($statement);
        $defaults = Settings::defaults($profile->grammar);
        $settings = new Settings($defaults->connection, userVariables: [], parameters: array_fill(0, (new Bindings())->count($statement), Domain::null()), client: $defaults->connection->charset, strict: false, explicitDefaultsForTimestamp: $defaults->explicitDefaultsForTimestamp, noZeroDate: false, noZeroInDate: false);
        return new Model(new AnalysisContext($profile, [new Name($choices->one(['d', 'shop']))], session: $settings), $statement);
    }
}
