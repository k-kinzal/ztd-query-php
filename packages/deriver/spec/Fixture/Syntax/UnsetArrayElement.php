<?php

function subject() { $a = ["keep" => 7, "drop" => 8]; unset($a["drop"]); return $a; }
