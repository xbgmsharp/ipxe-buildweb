iPXE Prebuilt binary web interface
=====

## Why
A Prebuilt binary web interface. Many users would prefer to be able to download prebuilt binary versions of iPXE, rather than building it from source.

## What
A web-based user interface that provide a way for the user to select any relevant iPXE build options, specify any embedded script, etc, and then construct and download the appropriate file.

## How
The user interface, is using HTML, CSS as well as Javascript (jQuery) and a suitable server-side language (such as Perl and PHP).
The build.fcgi script is written in Perl and was wrote by Michael Brown. 

## Test
You can acces it via [rom-o-matic.eu](http://rom-o-matic.eu)

## Contributing

1. Fork it
2. Create a branch (`git checkout -b my_markup`)
3. Commit your changes (`git commit -am "Added Snarkdown"`)
4. Push to the branch (`git push origin my_markup`)
5. Create an [Issue][1] with a link to your branch
6. Or Send me a [Pull Request][2]

[1]: https://github.com/xbgmsharp/ipxe-buildweb/issues
[2]: https://github.com/xbgmsharp/ipxe-buildweb/pull/new/master

## License
This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

