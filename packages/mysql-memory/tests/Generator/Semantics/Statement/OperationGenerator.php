<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueOptions;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use InvalidArgumentException;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Validation\Equivalence;
use Tests\Generator\MySql\Statement\Name\ColumnNameGenerator;
use Tests\Generator\MySql\Statement\Table\CreateTableGenerator;

/**
 * Publishes generated legacy statements in a fixed empty database context.
 * Concrete migrated statements use their own generators; Operation remains transitional.
 *
 * @implements ErisGenerator<Operation>
 */
final class OperationGenerator implements ErisGenerator
{
    /**
     * @var ErisGenerator<Operation>
     */
    private ErisGenerator $delegate;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Statement>|null $statements The statements to publish in a matching context
     */
    public function __construct(GrammarRelease $release, ?ErisGenerator $statements = null)
    {
        $this->delegate = Generators::map(
            static fn (\SqlSemantics\Statement\Statement $statement): Operation => new Operation(
                new AnalysisContext(
                    new LanguageProfile($release),
                    [$statement instanceof CreateTable ? ($statement->name->schema ?? new Name('pbt')) : new Name('pbt')],
                    session: new Settings(Collation::known('utf8mb4_unicode_ci'), client: Charset::known('utf8mb4')),
                ),
                $statement,
            ),
            $statements ?? new CreateTableGenerator(new ColumnNameGenerator($release)),
        );
    }

    /**
     * @param int $size The generation size chosen by Eris
     * @return GeneratedValueSingle<Operation>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * Rebuilds facts for each smaller statement; identical statements are not shrink candidates.
     * Operation facts contain node identity indexes, so comparing rebuilt operations cannot detect this.
     *
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<Operation>
     * @throws InvalidArgumentException When the value did not come from this generator
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        $operation = $element->unbox();
        if (!$operation instanceof Operation) {
            throw new InvalidArgumentException('Expected an operation generated from a statement.');
        }
        $candidates = [];
        foreach ($this->delegate->shrink($element) as $candidate) {
            if ((new Equivalence())->difference($operation->statement, $candidate->unbox()->statement) !== null) {
                $candidates[] = $candidate;
            }
        }
        return new GeneratedValueOptions($candidates);
    }
}
