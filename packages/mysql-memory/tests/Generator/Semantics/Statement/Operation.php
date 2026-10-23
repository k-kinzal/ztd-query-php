<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement;

use JsonException;
use LogicException;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operation as Model;
use Tests\Generator\Catalogue;
use Tests\Generator\Choices;
use Tests\Generator\Construction;
use Tests\Generator\Inhabitant;
use Tests\Generator\Metadata;
use Tests\Generator\Profiles;

/**
 * An operation's context must use a language profile that admits its statement.
 */
final class Operation implements Inhabitant
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
        $profile = (new Profiles())->select($statement);
        return new Model(new AnalysisContext($profile, [new Name($choices->one(['d', 'shop']))], complete: $choices->integer(0, 1) === 1), $statement);
    }
}
