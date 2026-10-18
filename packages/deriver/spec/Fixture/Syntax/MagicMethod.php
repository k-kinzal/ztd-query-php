<?php

class Box { public static function read() { return __METHOD__; } }
function subject() { return Box::read(); }
