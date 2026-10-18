<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Evidence;

/**
 * How an evidence node supports a value or a calling context.
 * @example Reading a classification identifier
 *     \Deriver\Deriver\Evaluator\Evidence\EvidenceKind::ArgumentBinding->value // => 'argument-binding'
 * @visibility public
 */
enum EvidenceKind: string
{
    case ArgumentBinding = 'argument-binding';
    case Call = 'call';
    case Capture = 'capture';
    case Choice = 'choice';
    case ContextAnd = 'context-and';
    case ContextAny = 'context-any';
    case ContextCall = 'context-call';
    case ContextOr = 'context-or';
    case ExternalInput = 'external-input';
    case EvaluatorApplication = 'evaluator-application';
    case Observation = 'observation';
    case Operation = 'operation';
    case ReferenceExpansion = 'reference-expansion';
    case SourceDefinition = 'source-definition';
    case StorageWrite = 'storage-write';
    case Unexpanded = 'unexpanded';
}
