#!/usr/bin/env perl
#
# Generates list of options from header file
#
# Initial version by Francois Lacroix <xbgmsharp@gmail.com>
#------------------------------------------------------------------------
# Dynamic iPXE image generator
#
# Copyright (C) 2012-2019 Francois Lacroix. All Rights Reserved.
# License:  GNU General Public License version 3 or later; see LICENSE.txt
# Website:  http://ipxe.org, https://github.com/xbgmsharp/ipxe-buildweb
#------------------------------------------------------------------------
### Dependencies
# apt-get install libjson-perl
# or
# perl -MCPAN -e 'install JSON'
### Install
# Copy the script into the ipxe source eg: /var/tmp/ipxe/src/util/
### Run
# The script is run by options.php

use strict;
use warnings;
use autodie;
use v5.10;
#use Data::Dumper;
use JSON;

my $bool; # list of define value
#$def{bool} = \@bool;

my $directory = '/var/tmp/ipxe/src/config';
opendir (DIR, $directory) or die $!;
while (my $file = readdir(DIR))
{
	next if ($file =~ m/^\./);
	next unless ($file =~ m/.h$/);
	next if ($file =~ m/colour/); # File we skip
	#print $file . "\n";
	#my $file = "general.h";
	open (FILE, $directory."/".$file);
	while (my $line = <FILE>) {
		chomp($line);
		# skip blank lines
		next if ($line =~ m/^$/);
		if ($line =~ /#define/)
		{
			#print $line . "\n";
                        if ($line =~ /([a-zA-Z_\/\/\#]*)(\t+|\s+)(\w*)\t+(\W+)([a-zA-Z0-9_\-\'\:\=\,\>\(\)\!\/ ]+)/g)
			{
				#print "----------Found in 2\n";
				#print "1 - $1\n";
				#print "3 - $3\n";
				#print "5 - $5\n";
				my $type = $1;
				my $name = $3;
				my $desc = $5;
				if ($type =~ /define/)
				{
					if ($type =~ /^\/\/\#/) # If comment then undef
					{
						#print "Add bool undef-------------------------------\n";
						push(@$bool, {file=> $file, type => "undef", name => $name, description => $desc});
					} else {
						#print "Add bool define-------------------------------\n";
						push(@$bool, {file=> $file, type => "define", name => $name, description => $desc});
					}
				}
                                if ($type !~ /define/)
                                {
                                        #print "Add input-------------------------------\n";
                                        push(@$bool, {file=> $file, type => "input", name => $type, value => $name, description => $desc});
                                }
                        }
			elsif ($line =~ /([a-zA-Z_]*)(\t+|\s+)([a-zA-Z0-9\:\/\"\.\% ]+)$/g)
			{
				#print "----------Found in 1\n";
				#print "1 - $1\n";
				#print "3 - $3\n";
				push(@$bool, {file=> $file, type => "input", name => $1, value => $3, description => $1});
			}

		}
		if ($line =~ /#undef/)
		{
			#print $line . "\n";
			if ($line =~ /([a-zA-Z]*)(\t+|\s+)(\w*)\t+(\W+)([a-zA-Z0-9_\- ]+)/g)
			{
				#print "1 - $1\n";
				#print "3 - $3\n";
				#print "5 - $5\n";
				push(@$bool, {file=> $file, type => $1, name => $3, description => $5});
				if ($1 !~ /undef/)
				{
					#print "to FIXE-------------------------------\n";
					pop(@$bool);
				}
			}	
		}
	}
	close (FILE);
}
closedir(DIR);

#print Dumper $bool;
#foreach my $options ( @$bool ) {
#	print "$options->{'name'}\t$options->{'description'}\n";
#}

print JSON->new->pretty->utf8->encode(\@$bool);

exit;
