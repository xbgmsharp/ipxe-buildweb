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

$cache_file = "/tmp/ipxeoptions";
$cache_life = '3600'; //caching time, in seconds, 1h

$filemtime = @filemtime($cache_file);  // returns FALSE if file does not exist
if (!$filemtime or (time() - $filemtime >= $cache_life))
{
	$outpout = exec("rm -f /tmp/ipxeoptions && cd /var/tmp/ipxe/src/ && perl /var/tmp/ipxe/src/util/parseheaders.pl 1> /tmp/ipxeoptions");
	readfile("/tmp/ipxeoptions");
} else {
	readfile("/tmp/ipxeoptions");
}

?>
