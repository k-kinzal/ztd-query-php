<?php

function nextValue() { static $value = 6; return ++$value; }
function subject() { return nextValue(); }
