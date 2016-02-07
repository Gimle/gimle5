<?php
namespace gimle;

header('Content-type: application/atom+xml; charset=' . mb_internal_encoding());
?>%content%<?php
return true;
