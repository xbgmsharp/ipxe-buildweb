#!/bin/bash
#------------------------------------------------------------------------
# Dynamic iPXE image generator
#
# Copyright (C) 2012-2021 Francois Lacroix. All Rights Reserved.
# License:  GNU General Public License version 3 or later; see LICENSE.txt
# Website:  http://ipxe.org, https://github.com/xbgmsharp/ipxe-buildweb
#------------------------------------------------------------------------

# Fix "Error debconf: unable to initialize frontend: Dialog"
echo 'debconf debconf/frontend select Noninteractive' | debconf-set-selections

# Upgrade system
apt-get update && apt-get -yq dist-upgrade

# Install basic compilation tools and dev libraries
apt install -yq build-essential \
                    iasl lzma-dev mtools perl python3 \
                    subversion uuid-dev liblzma-dev mtools
#apt-get -y install make gcc zlib1g-dev libc6-dev libssl-dev libstdc++6-4.7-dev libc-dev-bin liblzma-dev

# Install CGI Perl dependencies
apt-get -yq install liburi-perl \
                      libfcgi-perl \
                      libconfig-inifiles-perl \
                      libipc-system-simple-perl \
                      libsub-override-perl \
                      libcgi-pm-perl

#  Prepare iPXE directory
mkdir -p /var/cache/ipxe-build /var/run/ipxe-build /var/tmp/ipxe-build
rm -rf /var/cache/ipxe-build/* /var/run/ipxe-build/* /var/tmp/ipxe-build/*

# Install Git tools
apt-get -y install git

# Prepare the git iPXE repository
cd /var/tmp/ && rm -rf ipxe && git clone https://github.com/ipxe/ipxe/
touch /var/run/ipxe-build/ipxe-build-cache.lock
chown -R www-data:www-data /var/run/ipxe-build/ipxe-build-cache.lock \
                           /var/cache/ipxe-build \
                           /var/run/ipxe-build \
                           /var/tmp/ipxe-build /var/tmp/ipxe

# Install Apache with fast CGI and PHP module
#apt-get -y install libapache2-mod-fcgid libapache2-mod-php5 && a2enmod fcgid php5
#apt-get -yq install libapache2-mod-fcgid libapache2-mod-php && a2enmod fcgid php7.4
apt-get -yq install libapache2-mod-fcgid libapache2-mod-php && a2enmod fcgid php

# Install JSON library Perl
apt-get -yq install libjson-perl libjson-any-perl libjson-xs-perl

# Install extra packages to allow to build ISO and EFI binary
apt-get -yq install binutils-dev genisoimage syslinux isolinux

# Prepare the git buildweb repository
mkdir -p /var/www && cd /var/www && git clone https://github.com/xbgmsharp/ipxe-buildweb.git
cd /var/www/ipxe-buildweb
cp parseheaders.pl /var/tmp/ipxe/src/util/

# List required binaries
which perl make git syslinux

# message
echo -e "\nYou can now configure your webserver Apache.\nImportant directories:\n\t/var/cache/ipxe-build /var/run/ipxe-build /var/tmp/ipxe-build /var/www/ipxe-buildweb"

# setting up apache2
cat << EOF > /etc/apache2/sites-enabled/000-default.conf
<VirtualHost *:80>
	ServerAdmin webmaster@localhost
	DocumentRoot /var/www/ipxe-buildweb

	ErrorLog ${APACHE_LOG_DIR}/error.log
	CustomLog ${APACHE_LOG_DIR}/access.log combined

</VirtualHost>
EOF

cat << EOF > /etc/apache2/mods-enabled/fcgid.conf
<IfModule mod_fcgid.c>
    FcgidConnectTimeout 120
    FcgidIdleTimeout 3600
    FcgidBusyTimeout 300
    FcgidIOTimeout 360
    FcgidMaxRequestLen 15728640
    <IfModule mod_mime.c>
        AddHandler fcgid-script .fcgi
    </IfModule>
    <Files ~ (\.fcgi)>
        SetHandler fcgid-script
        Options +FollowSymLinks +ExecCGI
    </Files>
</IfModule>
EOF

DIRECTORIES="/var/tmp/ipxe-build /var/tmp/ipxe"
for DIRECTORY in ${DIRECTORIES}
do
 if [[ ! -d "${DIRECTORY}" ]]; then
     echo "Error, missing directory ${DIRECTORY}"
     exit 1
 fi
done
