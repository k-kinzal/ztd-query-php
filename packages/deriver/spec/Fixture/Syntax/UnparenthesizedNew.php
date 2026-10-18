<?php

class Box { public function read() { return 7; } }
function subject() { return new Box()->read(); }
