#!/bin/bash

# Upgrade system
apt-get update & apt-get -y dist-upgrade

# Install basic compilation tools and dev libraries
apt-get -y install make gcc zlib1g-dev libc6-dev libssl-dev libstdc++6-4.7-dev libc-dev-bin

# Install CGI Perl dependencies
apt-get -y install liburi-perl libfcgi-perl libconfig-inifiles-perl libipc-system-simple-perl libsub-override-perl

#  Prepare iPXE directory
mkdir -p /var/cache/ipxe-build /var/run/ipxe-build /var/tmp/ipxe-build
rm -rf /var/cache/ipxe-build/* /var/run/ipxe-build/* /var/tmp/ipxe-build/*

# Install Git tools
apt-get -y install git-core

# Prepare the git iPXE repository
cd /var/tmp/ && rm -rf ipxe && git clone https://git.ipxe.org/ipxe.git
touch /var/run/ipxe-build/ipxe-build-cache.lock
chown -R www-data:www-data /var/run/ipxe-build/ipxe-build-cache.lock /var/cache/ipxe-build /var/run/ipxe-build /var/tmp/ipxe-build /var/tmp/ipxe

# Install Apache 2 with fast CGI and PHP5 module
apt-get -y install libapache2-mod-fcgid libapache2-mod-php5 && a2enmod fcgid php5

# Install JSON library Perl
apt-get -y install libjson-perl libjson-any-perl libjson-xs-perl

# Install to allow to build ISO and EFI binary
apt-get -y install binutils-dev genisoimage syslinux

# Prepare the git buildweb repository
mkdir -p /var/www/ipxe-buildweb
git clone https://github.com/xbgmsharp/ipxe-buildweb.git
cp parseheaders.pl /var/tmp/ipxe/src/util/

# message
echo -e "\nYou can now configure your webserver Apache.\nImportant directories:\n\t/var/cache/ipxe-build /var/run/ipxe-build /var/tmp/ipxe-build /var/www/ipxe-buildweb"
