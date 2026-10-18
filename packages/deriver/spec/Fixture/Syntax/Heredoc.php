<?php

function subject() {
$name = "world";
return <<<TEXT
hello {$name}
TEXT;
}
