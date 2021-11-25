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

$cache_file = "/tmp/ipxelistnics";
$cache_life = '3600'; //caching time, in seconds, 1h

$filemtime = @filemtime($cache_file);  // returns FALSE if file does not exist
if (!$filemtime or (time() - $filemtime >= $cache_life))
{
	$outpout = exec("rm -f /tmp/ipxelistnics && cd /var/tmp/ipxe/src/ && /var/tmp/ipxe/src/util/niclist.pl --format json --columns ipxe_name,device_id,vendor_id 1>/tmp/ipxelistnics");
	readfile("/tmp/ipxelistnics");
} else {
	readfile("/tmp/ipxelistnics");
}

?>
