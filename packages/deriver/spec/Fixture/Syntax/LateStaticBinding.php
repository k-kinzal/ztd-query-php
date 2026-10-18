<?php

class Base { public static function read() { return static::VALUE; } } class Box extends Base { public const VALUE = 7; }
function subject() { return Box::read(); }
