<?php

class Box { public int $value = 7; }
function subject() { return (new Box())->value; }
