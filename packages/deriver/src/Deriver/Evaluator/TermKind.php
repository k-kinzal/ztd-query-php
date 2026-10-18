<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator;

/**
 * The expression categories retained by value analysis.
 * @visibility public
 * @example Inspecting a parameter expression
 *     \Deriver\Deriver\Evaluator\Term::parameter('id')->kind === \Deriver\Deriver\Evaluator\TermKind::Parameter // => true
 */
enum TermKind: string
{
    case Abstract = 'abstract';
    case Alternative = 'alternative';
    case Append = 'append';
    case ArgumentUnpack = 'argument-unpack';
    case Array = 'array';
    case ArrayContinuation = 'array-continuation';
    case ArrayKey = 'array-key';
    case ArrayMerge = 'array-merge';
    case ArrayRead = 'array-read';
    case ArraySet = 'array-set';
    case ArrayUnpack = 'array-unpack';
    case ArrayUnset = 'array-unset';
    case Binary = 'binary';
    case Bottom = 'bottom';
    case Call = 'call';
    case CallWrite = 'call-write';
    case Callable = 'callable';
    case CallableMethod = 'callable-method';
    case CaptureReference = 'capture-reference';
    case Cast = 'cast';
    case Cell = 'cell';
    case Choice = 'choice';
    case ClassConstant = 'class-constant';
    case Clone = 'clone';
    case Closure = 'closure';
    case Concat = 'concat';
    case Constant = 'constant';
    case ControlledValue = 'controlled-value';
    case Deferred = 'deferred';
    case Domain = 'domain';
    case Enum = 'enum';
    case External = 'external';
    case Finally = 'finally';
    case GlobalWrite = 'global-write';
    case Increment = 'increment';
    case Input = 'input';
    case Instanceof = 'instanceof';
    case Intrinsic = 'intrinsic';
    case Iterate = 'iterate';
    case Iterator = 'iterator';
    case IteratorAddress = 'iterator-address';
    case IteratorKey = 'iterator-key';
    case IteratorValue = 'iterator-value';
    case Location = 'location';
    case NoValue = 'no-value';
    case Object = 'object';
    case Omitted = 'omitted';
    case Opaque = 'opaque';
    case Operation = 'operation';
    case Parameter = 'parameter';
    case Projection = 'projection';
    case PropertyWrite = 'property-write';
    case Recurrence = 'recurrence';
    case Recursive = 'recursive';
    case Reference = 'reference';
    case StateInput = 'state-input';
    case StateRead = 'state-read';
    case StaticUninitialized = 'static-uninitialized';
    case Throwable = 'throwable';
    case Top = 'top';
    case Tuple = 'tuple';
    case TypeRefinement = 'type-refinement';
    case Unary = 'unary';
    case UnchangedState = 'unchanged-state';
    case UnexpandedChoice = 'unexpanded-choice';
    case Uninitialized = 'uninitialized';
    case Write = 'write';
}
