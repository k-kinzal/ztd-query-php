<?php

#[Attribute(Attribute::TARGET_FUNCTION)] class Marker {}
#[Marker]
function subject() { return 7; }
