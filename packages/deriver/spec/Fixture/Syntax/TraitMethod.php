<?php

trait Value { public function read() { return 7; } } class Box { use Value; }
function subject() { return (new Box())->read(); }
