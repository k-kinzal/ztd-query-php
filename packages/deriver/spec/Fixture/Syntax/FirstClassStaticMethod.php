<?php

class Box { public static function read() { return 7; } }
function subject() { $call = Box::read(...); return $call(); }
