<?php

class Box { public static function read() { return __CLASS__; } }
function subject() { return Box::read(); }
