<?php

declare(strict_types=1);

namespace Tests\Fake\Programs;

/**
 * Trusted PHP 8.3 defaults that construct objects in the declaring file's scalar mode.
 * @visibility root
 */
final class InitializerPrograms
{
    /**
     * @return array<string,array{string,string}> Source and independently expected JSON return
     */
    public static function cases(): array
    {
        return [
            'strict function default' => ['<?php declare(strict_types=1);class Box{function __construct(public int $value){}}function helper($box=new Box("7")){return $box->value;}function target(){try{return helper();}catch(TypeError $e){return "error";}}','"error"'],
            'weak function default' => ['<?php class Box{function __construct(public int $value){}}function helper($box=new Box("7")){return $box->value;}function target(){return helper();}','7'],
            'strict method default' => ['<?php declare(strict_types=1);class Box{function __construct(public int $value){}}class Factory{function make($box=new Box("7")){return $box->value;}}function target(){try{$f=new Factory;return $f->make();}catch(TypeError $e){return "error";}}','"error"'],
            'strict static method default' => ['<?php declare(strict_types=1);class Box{function __construct(public int $value){}}class Factory{static function make($box=new Box("7")){return $box->value;}}function target(){try{return Factory::make();}catch(TypeError $e){return "error";}}','"error"'],
            'strict closure default' => ['<?php declare(strict_types=1);class Box{function __construct(public int $value){}}function target(){$f=function($box=new Box("7")){return $box->value;};try{return $f();}catch(TypeError $e){return "error";}}','"error"'],
            'strict arrow default' => ['<?php declare(strict_types=1);class Box{function __construct(public int $value){}}function target(){$f=fn($box=new Box("7"))=>$box->value;try{return $f();}catch(TypeError $e){return "error";}}','"error"'],
            'strict promoted default' => ['<?php declare(strict_types=1);class Box{function __construct(public int $value){}}class Holder{function __construct(public Box $box=new Box("7")){}}function target(){try{$h=new Holder;return $h->box->value;}catch(TypeError $e){return "error";}}','"error"'],
            'supplied argument skips invalid default' => ['<?php declare(strict_types=1);class Box{function __construct(public int $value){}}function helper($box=new Box("7")){return $box->value;}function target(){return helper(new Box(9));}','9'],
            'fresh default each invocation' => ['<?php class Box{}function helper($box=new Box){return $box;}function target(){$a=helper();$b=helper();return $a!==$b;}','true'],
            'default fails before constructor body' => ['<?php declare(strict_types=1);class Box{function __construct(int $value){$GLOBALS["calls"]++;}}function helper($box=new Box("7")){return 9;}function target(){$GLOBALS["calls"]=0;try{helper();}catch(TypeError $e){return $GLOBALS["calls"];}return 99;}','0'],
        ];
    }
    /**
     * @return array<string,array{string,string,string}> Declaration file, caller file, and expected JSON
     */
    public static function fileCases(): array
    {
        $result = [];
        foreach ([0,1] as $declaration) {
            foreach ([0,1] as $caller) {
                $library = '<?php declare(strict_types='.$declaration.');class Box{function __construct(public int $value){}}function helper($box=new Box("7")){return $box->value;}';
                $application = '<?php declare(strict_types='.$caller.');function target(){try{return helper();}catch(TypeError $e){return "error";}}';
                $result['declaration '.$declaration.' caller '.$caller] = [$library,$application,$declaration === 1 ? '"error"' : '7'];
            }
        }
        return $result;
    }
}
