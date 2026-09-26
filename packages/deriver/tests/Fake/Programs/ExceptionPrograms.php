<?php

declare(strict_types=1);

namespace Tests\Fake\Programs;

/**
 * Trusted PHP 8.3 exception validation and finally chaining fixtures.
 * @visibility root
 */
final class ExceptionPrograms
{
    /**
     * @return array<string,array{string,string}> Source and independently specified JSON result
     */
    public static function cases(): array
    {
        return [
            'class named nonthrowable' => ['<?php class nonthrowable extends Exception{}function target(){try{throw 7;}catch(Error $e){return 1;}return 2;}', '1'],
            'finally prevents previous cycle' => ['<?php function target(){$old=new RuntimeException;$first=new LogicException("first",0,$old);try{try{throw $first;}finally{throw $old;}}catch(RuntimeException $caught){return $caught->getPrevious();}}', 'null'],
            'finally keeps an existing previous' => ['<?php function target(){$first=new RuntimeException;$last=new LogicException("last",0,$first);try{try{throw $first;}finally{throw $last;}}catch(LogicException $caught){$previous=$caught->getPrevious();$tail=$previous->getPrevious();return [$previous===$first,$tail];}}', '[true,null]'],
            'integer throw' => ['<?php function target(){try{throw 7;}catch(Error $e){return 1;}return 2;}', '1'],
            'null throw' => ['<?php function target(){try{throw null;}catch(Error $e){return 1;}return 2;}', '1'],
            'string throw' => ['<?php function target(){try{throw "text";}catch(Error $e){return 1;}return 2;}', '1'],
            'array throw' => ['<?php function target(){try{throw [];}catch(Error $e){return 1;}return 2;}', '1'],
            'ordinary object throw' => ['<?php function target(){try{throw new stdClass;}catch(Error $e){return 1;}return 2;}', '1'],
            'closure throw' => ['<?php function target(){try{throw fn()=>1;}catch(Error $e){return 1;}return 2;}', '1'],
            'enum throw' => ['<?php enum Mode{case Ready;}function target(){try{throw Mode::Ready;}catch(Error $e){return 1;}return 2;}', '1'],
            'throwable identity retained' => ['<?php function target(){$original=new RuntimeException;try{throw $original;}catch(Exception $caught){return $caught===$original;}}', 'true'],
            'finally chains previous' => ['<?php function target(){try{try{throw new RuntimeException("first");}finally{throw new LogicException("second");}}catch(LogicException $e){return $e->getPrevious() instanceof RuntimeException;}}', 'true'],
            'finally retains previous identity' => ['<?php function target(){$first=new RuntimeException;try{try{throw $first;}finally{throw new LogicException;}}catch(LogicException $e){$previous=$e->getPrevious();return $previous===$first;}}', 'true'],
            'finally chains native error' => ['<?php function target(){try{try{$x=1/0;}finally{throw new LogicException;}}catch(LogicException $e){return $e->getPrevious() instanceof DivisionByZeroError;}}', 'true'],
            'finally error chains exception' => ['<?php function target(){try{try{throw new RuntimeException;}finally{throw new Error;}}catch(Error $e){return $e->getPrevious() instanceof RuntimeException;}}', 'true'],
            'finally keeps existing chain' => ['<?php function target(){$old=new Exception("old");$new=new LogicException("new",0,$old);$first=new RuntimeException("first");try{try{throw $first;}finally{throw $new;}}catch(LogicException $e){$previous=$e->getPrevious();$tail=$old->getPrevious();return [$previous===$old,$tail===$first];}}', '[true,true]'],
            'finally nested chain' => ['<?php function target(){try{try{try{throw new RuntimeException;}finally{throw new LogicException;}}finally{throw new Error;}}catch(Error $e){return [$e->getPrevious() instanceof LogicException,$e->getPrevious()->getPrevious() instanceof RuntimeException];}}', '[true,true]'],
            'finally same object rethrow' => ['<?php function target(){$e=new RuntimeException;try{try{throw $e;}finally{throw $e;}}catch(RuntimeException $caught){return [$caught===$e,$caught->getPrevious()];}}', '[true,null]'],
            'finally return has no previous' => ['<?php function target(){try{try{return 1;}finally{throw new RuntimeException;}}catch(RuntimeException $e){return $e->getPrevious();}}', 'null'],
        ];
    }
}
