<?php
namespace gimle;

$res = Spectacle::read(page('id'));
if ($res === false) {
	echo json_encode(false);
}
echo trim($res);

return true;
