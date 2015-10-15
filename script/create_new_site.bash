#!/bin/bash

echo -n "Enter site name: "
read name
echo ""

cd ../../../../

if [ -e ./${name} ]; then
	echo "Site name in use."
	exit 1
fi

mkdir ${name}
cd ${name}
git init
mkdir module
cd module
git submodule add https://github.com/Gimle/gimle5.git
cd ..
mkdir canvas
mkdir public
mkdir template

cat <<EOF > .gitignore
*.ini
.*
!.gitignore
EOF

cat <<EOF > public/index.php
<?php
namespace gimle;

use gimle\router\Router;

/**
 * The local absolute location of the site.
 *
 * @var string
 */
define('gimle\\SITE_DIR', substr(__DIR__, 0, strrpos(__DIR__, DIRECTORY_SEPARATOR) + 1));

try {
	require SITE_DIR . 'module/gimle5/init.php';
	\$router = Router::getInstance();

	\$router->dispatch();
} catch (\gimle\router\Exception \$e) {
} catch (\Exception \$e) {
}
EOF

cat <<EOF > config.ini
[base.pc]
start = "http://"
path = "http://${HOSTNAME}/${name}/"
EOF

echo "Site ${name} created."
exit
