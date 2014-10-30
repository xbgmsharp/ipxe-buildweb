#!/bin/bash

# upgrade system
apt-get update
apt-get dist-upgrade

# Install basic complication tools
apt-get install make gcc

#  Prepare iPXE directory
apt-get install liburi-perl libfcgi-perl libconfig-inifiles-perl libipc-system-simple-perl libsub-override-perl
mkdir -p /var/cache/ipxe-build /var/run/ipxe-build /var/tmp/ipxe-build
rm -rf /var/cache/ipxe-build/* /var/run/ipxe-build/* /var/tmp/ipxe-build/*

# Prepare the git iPXE repository
apt-get install git-core
cd /var/tmp/ && rm -rf ipxe && git clone https://git.ipxe.org/ipxe.git
touch /var/run/ipxe-build/ipxe-build-cache.lock
chown -R www-data:www-data /var/run/ipxe-build/ipxe-build-cache.lock /var/cache/ipxe-build /var/run/ipxe-build /var/tmp/ipxe-build /var/tmp/ipxe

# Apache 2 wtih fast CGI module
apt-get install libapache2-mod-fcgid && a2enmod fcgid

# JSON library Perl
apt-get install libjson-perl libjson-any-perl libjson-xs-perl

# Allow to build ISO and EFI binary
apt-get install binutils-dev genisoimage

# Prepare the git buildweb repository
mkdir -p /var/www/ipxe-buildweb
git clone https://github.com/xbgmsharp/ipxe-buildweb.git

cp parseheaders.pl /var/tmp/ipxe/src/util/

