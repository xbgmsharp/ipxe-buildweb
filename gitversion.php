<?php
/*
#------------------------------------------------------------------------
# Dynamic iPXE image generator
#
# Copyright (C) 2012-2021 Francois Lacroix. All Rights Reserved.
# License:  GNU General Public License version 3 or later; see LICENSE.txt
# Website:  http://ipxe.org, https://github.com/xbgmsharp/ipxe-buildweb
# Support:  xbgmsharp@gmail.com
#------------------------------------------------------------------------
*/

//$output = exec('cd /var/tmp/ipxe/src/ && git describe --always --abbrev=1 --match ""');
exec('cd /var/tmp/ipxe/src/ && git rev-list --all --max-count=20 --abbrev-commit --abbrev=1', $output);
//print_r($output);
echo json_encode($output);

?>


