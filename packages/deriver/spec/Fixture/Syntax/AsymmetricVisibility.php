<?php

class Box { public private(set) int $value = 7; }
function subject() { return (new Box())->value; }
