<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Hint\Comment;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes HintComment values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintComment>
 */
final class HintCommentGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintComment> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Hint\OptimizerHint>> $hints Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintError|null>|null $error Generated constructor operand
     */
    public function __construct(ErisGenerator $hints, ?ErisGenerator $error = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintComment {
            return new \SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintComment(hints: $parts[0], error: $parts[1]);
        }, Generators::tuple(
            $hints,
            $error ?? Generators::constant(null)
        ));
    }
    /**
     * Composes accepted hints and their stopping warning into the published query.
     * The warning is part of the generated meaning, with no source SQL to consult.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\QueryRoot>
     */
    public static function projections(): ErisGenerator
    {
        $comments = new self(Generators::vector(1, \Tests\Generator\MySql\Statement\Hint\OptimizerHintGenerator::namedForms()), new HintErrorGenerator(new HintFailureGenerator(), Generators::choose(0, 128), Generators::bool(), Generators::constant(true)));
        return Generators::map(static function (\SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintComment $comment): \SqlSemantics\Platform\MySql\Statement\QueryRoot {
            \SqlSemantics\Diagnostic\Check::invariant($comment->error !== null, 'The generated hint comment has a stopping warning.');
            $query = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))], hints: $comment->hints));
            $sources = new \SqlSemantics\Statement\Source\SourceMap(notices: [new \SqlSemantics\Statement\Source\SourceNotice($query, $comment->error, $comment->error->offset)]);
            $context = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, '8.4.7'))->context();
            $result = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($context, $query, $sources);
            \SqlSemantics\Diagnostic\Check::invariant($result instanceof \SqlSemantics\Platform\MySql\Statement\QueryRoot, 'Publication preserves the generated query.');
            return $result;
        }, $comments);
    }

}
