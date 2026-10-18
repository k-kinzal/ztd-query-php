<?php

function nextValue() { static $value = 6; return ++$value; }
function subject() { nextValue(); return nextValue(); }
