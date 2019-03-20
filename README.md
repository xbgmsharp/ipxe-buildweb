iPXE Prebuilt binary web interface
=====

## Why
A Prebuilt binary web interface. Many users would prefer to be able to download prebuilt binary versions of iPXE, rather than building it from source.

## What
A web-based user interface that provide a way for the user to select any relevant iPXE build options, specify any embedded script, etc, and then construct and download the appropriate file.

## How
The user interface, is using HTML, CSS as well as Javascript (jQuery) and a suitable server-side language (such as Perl and PHP).
All GUI options (git version/nics list/compile options) are generated dynamicaly using PHP.
The build.fcgi script is written in Perl and was wrote by Michael Brown.

## Test
You can acces it via [rom-o-matic.eu](http://rom-o-matic.eu)

## Using Official DockerHub image

[![dockeri.co](https://dockeri.co/image/xbgmsharp/ipxe-buildweb)](https://hub.docker.com/r/xbgmsharp/ipxe-buildweb)

* Supported architectures: x86-64

* Run ipxe-buildweb
After a successful [Docker installation](https://docs.docker.com/engine/installation/) you just need to execute the following command in the shell:

```bash
docker pull xbgmsharp/ipxe-buildweb
docker run  -d \
	--publish 8080:80 \
	--publish 22:22 \
	--name ipxe-buildweb \
	xbgmsharp/ipxe-buildweb
```

## Test using Docker

* Install Docker
[Install documentation of Docker](https://docs.docker.com/engine/installation/)

The Docker deb package are valid for Ubuntu and Debian.

```bash
$ wget http://get.docker.io/ -O - | sh
```

* Build the images

The following command build the build directly from the github repository.

The build process might take some time a while as it download the origin Ubuntu LTS 14.04 docker image.
```bash
$ docker build --rm=true --no-cache=true -t xbgmsharp/ipxe-buildweb github.com/xbgmsharp/ipxe-buildweb.git
```

Alternatively, you can build the image localy after cloning the repository.
```bash
$ docker build --rm=true --no-cache=true -t xbgmsharp/ipxe-buildweb .
```

* Run the container

Run as a detach container
```bash
$ docker run -d -p 22:22 -p 8080:80 -t xbgmsharp/ipxe-buildweb
```

Or run the container with an attach shell
```
$ docker run -i --rm -p 22:22 -p 8080:80 -t xbgmsharp/ipxe-buildweb /bin/bash
```

* Check the IP

```bash
$ docker ps -a
$ docker inspect CONTAINER_ID | grep IPA
```

Or both command in one
```bash
$ docker ps -a | grep ipxe-buildweb | awk '{print $1}' | xargs docker inspect | grep IPAddress
```

Or all in one with the ssh connection
```bash
$ ssh $(docker ps -a | grep ipxe-buildweb | awk '{print $1}' | xargs docker inspect | grep IPAddress | awk '{print $2}' | tr -d '"' | tr -d ',' )
```

* Login in the container via SSH

User is root and password is admin.

```bash
$ ssh root@172.17.0.x
```

* Review logs
```bash
$ docker logs CONTAINER_ID
```

* Enjoy!

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
