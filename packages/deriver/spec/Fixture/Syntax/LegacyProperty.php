<?php

class Box { var $value = 7; }
function subject() { return (new Box())->value; }
