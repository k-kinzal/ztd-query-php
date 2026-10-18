<?php

trait Value { public function read() { return 7; } } class Box { use Value { read as alias; } }
function subject() { return (new Box())->alias(); }
