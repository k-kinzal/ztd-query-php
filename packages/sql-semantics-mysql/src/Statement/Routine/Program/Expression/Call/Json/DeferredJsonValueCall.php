<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\Json;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Conversion\CastNotices;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponse;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Type\Invalid;

/**
 * A JSON_VALUE instruction retaining a non-scalar document and its typed options.
 *
 * The document's condition remains deferred in stored or unused instructions.
 * The path, return target and fallback values remain available to consumers.
 * No expression is substituted to publish an ordinary scalar call.
 *
 * @visibility public
 * @example Retaining a document condition in an unexecuted instruction
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("CREATE PROCEDURE p() DO JSON_VALUE((1,2), '$')");
 *     $operation instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure // => true
 */
final class DeferredJsonValueCall implements DeferredOperand
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param RowOperand|DeferredOperand $document The non-scalar primary expression
     * @param StringLiteral $path The constant selection path
     * @param CastTarget|null $returning The return type, when written
     * @param JsonResponse|null $onEmpty The empty-path response
     * @param JsonResponse|null $onError The conversion-error response
     */
    public function __construct(public readonly RowOperand|DeferredOperand $document, public readonly StringLiteral $path, public readonly ?CastTarget $returning = null, public readonly ?JsonResponse $onEmpty = null, public readonly ?JsonResponse $onError = null, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        Check::input((new Precedence())->admits($document, Precedence::SIMPLE_EXPR), 'The document of JSON_VALUE needs a grouping.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives complete dependencies while retaining the document's preparation condition.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $document = $derivation->scalar($this->document, $environment);
        $derivation->scalar($this->path, $environment);
        foreach ([$this->onEmpty?->default, $this->onError?->default] as $default) {
            if ($default !== null) {
                $derivation->scalar($default, $environment);
            }
        }
        $problem = (new CastNotices())->returning($this->returning, $this->document, $derivation);
        if ($problem !== null) {
            return new ScalarFact(new Invalid($problem), $document->nullability);
        }
        return new ScalarFact(new Invalid($this->problem()), $document->nullability);
    }

    /**
     * @return non-empty-list<Scalar|RowOperand|DeferredOperand> Dependencies in preparation order
     */
    public function operands(): array
    {
        return [$this->document, $this->path];
    }

    /**
     * Answers the condition retained by the non-scalar document.
     */
    public function problem(): Diagnostic
    {
        return (new \SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments())->problem([$this->document]);
    }

    /**
     * Writes the complete call and every optional response.
     */
    public function render(Output $out): void
    {
        $out->keyword('JSON_VALUE')->glue()->symbol('(')->node($this->document)->symbol(',')->node($this->path);
        if ($this->returning !== null) {
            $out->keyword('RETURNING')->node($this->returning);
        }
        if ($this->onEmpty !== null) {
            $out->node($this->onEmpty)->keyword('ON', 'EMPTY');
        }
        if ($this->onError !== null) {
            $out->node($this->onError)->keyword('ON', 'ERROR');
        }
        $out->symbol(')');
    }
}
