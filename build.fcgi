#!/usr/bin/perl
#
# Build ipxe binary according to options
#
# Initial version by Michael Brown <mcb30@ipxe.org>
# Modified version by Francois Lacroix <xbgmsharp@gmail.com>
#------------------------------------------------------------------------
# Dynamic iPXE image generator
#
# Copyright (C) 2012-2021 Francois Lacroix. All Rights Reserved.
# License:  GNU General Public License version 3 or later; see LICENSE.txt
# Website:  http://ipxe.org, https://github.com/xbgmsharp/ipxe-buildweb
#------------------------------------------------------------------------
### Dependencies
# apt-get install liburi-perl libfcgi-perl libconfig-inifiles-perl libipc-system-simple-perl libsub-override-perl
# mkdir /var/cache/ipxe-build /var/run/ipxe-build /var/tmp/ipxe-build
# rm -rf /var/cache/ipxe-build/* /var/run/ipxe-build/* /var/tmp/ipxe-build/*
# apt-get install git-core
# cd /var/tmp/ && rm -rf ipxe && git clone https://git.ipxe.org/ipxe.git
# touch /var/run/ipxe-build/ipxe-build-cache.lock
# chown -R www-data:www-data /var/run/ipxe-build/ipxe-build-cache.lock /var/cache/ipxe-build /var/run/ipxe-build /var/tmp/ipxe-build /var/tmp/ipxe
### Apache
# apt-get install libapache2-mod-fcgid && a2enmod fcgid
#        <Directory /var/www/ipxe/>
#                AllowOverride None
#                AddHandler fcgid-script .fcgi
#                Options +ExecCGI +Includes +FollowSymLinks
#                Order allow,deny
#                Allow from all
#        </Directory>
### Sample usage
# $ perl build.fcgi BINARY=ipxe.iso BINDIR=bin REVISION=master DEBUG= EMBED.00script.ipxe='%23!ipxe%0Aecho "Dynamic iPXE image generator"%0Adhcp%0Aconfig%0A%0A%0A' \
#    general.h/VLAN_CMD:=1 general.h/REBOOT_CMD:=1 general.h/PRODUCT_NAME=Build general.h/PRODUCT_SHORT_NAME=iPXE3.0 general.h/BANNER_TIMEOUT=90

use CGI qw ( :cgi );
use FCGI;
use URI;
use Getopt::Long;
use Config::IniFiles;
use File::Temp;
use File::Spec::Functions qw ( tmpdir catdir catfile splitpath );
use File::Find;
use File::stat;
use Fcntl qw ( :flock );
use IO::File;
use IO::Seekable;
use IO::Compress::Gzip qw ( gzip $GzipError );
use IPC::System::Simple qw ( systemx capturex );
use POSIX qw ( nice strftime );
use Sub::Override;
use strict;
use warnings;

# Parse command line options
my $verbosity = 2;
my $cfgfile = "build.ini";
my $foreground = 0;
my $keep;
Getopt::Long::Configure ( "bundling", "auto_abbrev" );
GetOptions (
  "foreground|f" => sub { $foreground = 1; },
  "verbose|v+" => sub { $verbosity++; },
  "quiet|q+" => sub { $verbosity--; },
  "config|c=s" => sub { shift; $cfgfile = shift; },
  "keep|k" => sub { $keep = 1; },
) or die "Could not parse command-line options\n";

# Load configuration
my $cfg = Config::IniFiles->new ( -file => $cfgfile )
    or die join ( "\n", @Config::IniFiles::errors )."\n";
my $repository = $cfg->val ( "source", "repository" )
    or die "No repository specified in ".$cfgfile."\n";
my $niceness = $cfg->val ( "build", "niceness", 0 );
my $concurrency = $cfg->val ( "build", "concurrency" );
my $bindirs = $cfg->val ( "build", "bindirs", "bin" );
$bindirs = { map { $_ => 1 } split ( /\s+/, $bindirs ) };
my $tmpdir = $cfg->val ( "build", "tmpdir", tmpdir() );
my $cacheroot = $cfg->val ( "cache", "root" )
    or die "No cache root specified in ".$cfgfile."\n";
die "Cache root \"".$cacheroot."\" does not exist\n" unless -d $cacheroot;
my $lockfile = $cfg->val ( "cache", "lockfile" )
    or die "No lockfile specified in ".$cfgfile."\n";
open my $lockfh, "+>>", $lockfile
    or die "Could not open lockfile: $!\n";
my $cachemax = $cfg->val ( "cache", "max", 20 );

# Duplicate original STDIN, STDOUT and STDERR
open my $origstdin, "<&", \*STDIN or die "Could not dup STDIN: $!\n";
open my $origstdout, ">&", \*STDOUT or die "Could not dup STDOUT: $!\n";
open my $origstderr, ">&", \*STDERR or die "Could not dup STDERR: $!\n";

###############################################################################
#
# Manage cache lock
#

sub cache_lock {
  flock ( $lockfh, LOCK_EX ) or die "Cannot lock cache: $!\n";
}

sub cache_unlock {
  flock ( $lockfh, LOCK_UN ) or die "Cannot unlock cache: $!\n";
}

###############################################################################
#
# Load best available cached binaries
#

sub load_cached_binaries {
  my $gitdir = shift;
  my $worktree = shift;
  my $revision = shift;
  my $bindir = shift;

  warn "Finding closest cached binaries for ".$revision."...\n"
      if $verbosity > 1;
  my $best_tag;
  my $best_distance;

  # Obtain cache lock
  cache_lock();

  # Find closest source before specified revision
  my $before;
  eval {
    $before = capturex ( "git", "--git-dir", $gitdir, "--work-tree", $worktree,
			 "describe", "--long", "--tags", "--match",
			 "ipxe-build/cached/".$bindir."/*", $revision );
    chomp $before;
  };
  if ( $before ) {
    ( my $tag, my $distance ) = ( $before =~ /^(.+)-(\d+)-g[0-9a-f]+$/ )
	or die "Invalid git description \"".$before."\"\n";
    $best_distance = $distance;
    $best_tag = $tag;
  }

  # Find closest source after specified revision
  my $after;
  eval {
    $after = capturex ( "git", "--git-dir", $gitdir, "--work-tree", $worktree,
			"describe", "--long", "--tags", "--contains", "--match",
			"ipxe-build/cached/".$bindir."/*", $revision );
    chomp $after;
  };
  if ( $after ) {
    ( my $tag, undef, my $distance ) = ( $after =~ /^(.+?)(~(\d+))?$/ )
	or die "Invalid git description \"".$after."\"\n";
    $distance ||= 0;
    if ( ( ! defined $best_distance ) || ( $distance < $best_distance ) ) {
      $best_distance = -$distance;
      $best_tag = $tag;
    }
  }

  # Do nothing if we have no cached binaries available
  if ( ! $best_tag ) {
    warn "Found no cached binaries\n" if $verbosity > 0;
    cache_unlock();
    return;
  }
  warn "Found cached binaries in ".$best_tag." at distance "
      .$best_distance."\n" if $verbosity > 0;

  # Identify revision with cached binaries
  ( my $cached, my $suffix ) = ( $best_tag =~ /\/([0-9a-f]+)(\.gz)?$/ )
      or die "Invalid ipxe-build/cached tag \"".$best_tag."\"\n";
  $suffix ||= "";

  # Open binary tarball (compressed or otherwise).
  my $tarball = catfile ( $cacheroot, $cached."-".$bindir.".tar".$suffix );
  warn "Opening binary tarball ".$tarball."...\n" if $verbosity > 1;
  my $tarfh;
  open $tarfh, "<", $tarball
      or die "Could not open ".$tarball.": $!\n";
  warn "Opened binary tarball ".$tarball."...\n" if $verbosity > 1;

  # Update tarball's timestamp if it is an exact match
  utime undef, undef, $tarfh if $cached eq $revision;

  # Release cache lock
  cache_unlock();

  # Check out commit corresponding to cached binaries.  Check out by
  # sha1 rather than tag, since we have released the cache lock and so
  # the tag may no longer exist.
  warn "Checking out revision ".$cached."...\n" if $verbosity > 1;
  systemx ( "git", "--git-dir", $gitdir, "--work-tree", $worktree,
	    "checkout", "--quiet", $cached );

  # Identify commit timestamp.  This is a timestamp that must
  # logically be older than any file in the corresponding binary
  # tarball.
  warn "Identifying timestamp for commit ".$cached."...\n" if $verbosity > 1;
  my $timestamp = capturex ( "git", "--git-dir", $gitdir, "--work-tree",
			     $worktree, "show", "-s", "--format=\%ct",
			     $cached );
  chomp $timestamp;

  # Set timestamps on all checked-out files to the commit timestamp
  warn "Setting timestamps to ".strftime ( "%c", localtime ( $timestamp ) )
      ."...\n" if $verbosity > 1;
  find ( { wanted => sub { utime $timestamp, $timestamp, $_; },
	   no_chdir => 1 }, $worktree );

  # Unpack binary tarball
  warn "Unpacking binary tarball ".$tarball."...\n" if $verbosity > 1;
  close STDIN;
  open STDIN, "<&", $tarfh or die "Could not dup tarfh: $!\n";
  systemx ( "tar", "-x", "-C", catdir ( $worktree ),
	    ( $suffix ? ( "-z" ) : () ) );
  close $tarfh;
  close STDIN;
  open STDIN, "<&", $origstdin or die "Could not restore STDIN: $!\n";

  return $cached;
}

###############################################################################
#
# Save cached binaries
#

sub save_cached_binaries {
  my $gitdir = shift;
  my $worktree = shift;
  my $revision = shift;
  my $bindir = shift;

  # Build blib.a
  warn "Building cacheable binaries...\n" if $verbosity > 1;
  systemx ( "make", "-C", catdir ( $worktree, "src" ),
	    ( $concurrency ? ( "-j", $concurrency ) : () ),
	    catfile ( $bindir, "blib.a" ) );

  # Generate file list
  warn "Generating binary file list...\n" if $verbosity > 1;
  my @files = capturex ( "git", "--git-dir", $gitdir, "--work-tree", $worktree,
			 "ls-files", "--others" );
  chomp @files;

  # Create tarball
  my $tarball = catfile ( $cacheroot, $revision."-".$bindir.".tar" );
  warn "Creating binary tarball ".$tarball."...\n" if $verbosity > 1;
  my $tarfh = File::Temp->new ( TEMPLATE => "ipxe-cache-XXXXXX",
				DIR => $cacheroot );
  chmod 0664, $tarfh or die "Could not set permissions: $!\n";
  systemx ( "tar", "-c", "-C", catdir ( $worktree ),
	    "-f", $tarfh->filename, @files );

  # Obtain cache lock
  cache_lock();

  # Move tarball into position
  if ( rename ( $tarfh->filename, $tarball ) ) {
    $tarfh->unlink_on_destroy ( 0 );
  } else {
    # Tarball has already been created concurrently - harmless waste
    warn "Could not create binary tarball ".$tarball.": $!\n";
  }
  undef $tarfh;

  # Create tag in upstream repository.  Do this even if tarball
  # already exists, in case a previous cache save managed to create
  # the tarball but failed to create the tag.  (There is no way to
  # make these two operations properly atomic.)
  my $tag = "ipxe-build/cached/".$bindir."/".$revision;
  warn "Creating tag ".$tag."...\n" if $verbosity > 1;
  systemx ( "git", "--git-dir", $repository, "tag", "--force",
	    $tag, $revision );

  # Obtain list of all cached binaries
  warn "Listing all cache tags...\n" if $verbosity > 1;
  my @candidates = map { chomp; ( $_ eq $tag ) ? () : ( { tag => $_ } ) }
		       capturex ( "git", "--git-dir", $repository, "tag", "-l",
				  "ipxe-build/cached/*" );

  # Find modification times for candidates
  foreach my $candidate ( @candidates ) {
    ( $candidate->{bindir}, $candidate->{revision}, my $suffix ) =
	( $candidate->{tag} =~
	  /^ipxe-build\/cached\/(.+?)\/([0-9a-f]+)(\.gz)?$/ )
	or die "Invalid tag name \"".$candidate->{tag}."\"\n";
    $suffix ||= "";
    $candidate->{tarball} = catfile ( $cacheroot, $candidate->{revision}."-".
				      $candidate->{bindir}.".tar".$suffix );
    my $stat = stat ( $candidate->{tarball} );
    if ( $stat ) {
      $candidate->{mtime} = $stat->mtime;
    } else {
      warn "Missing tarball ".$candidate->{tarball}."\n";
      $candidate->{mtime} = 0; # Treat as very old to force tag removal
    }
  }
  @candidates = sort { $a->{mtime} <=> $b->{mtime} } @candidates;

  # Expire oldest candidates to reduce cache size
  while ( @candidates >= $cachemax ) {
    my $candidate = shift @candidates;

    # Delete tag
    warn "Deleting tag ".$candidate->{tag}."...\n" if $verbosity > 1;
    systemx ( "git", "--git-dir", $repository, "tag", "-d", $candidate->{tag} );

    # Delete tarball
    warn "Deleting binary tarball ".$candidate->{tarball}."...\n"
	if $verbosity > 1;
    unlink ( $candidate->{tarball} );
  }

  # Start gzip running in background.  We leave this running when we
  # exit.
  start_gzip ( $revision, $bindir );

  # Release cache lock
  cache_unlock();
}

###############################################################################
#
# Run gzip in background
#
# We use gzip rather than bzip2 because the *decompression* speed for
# gzip is almost an order of magnitude faster than for bzip2.
#

sub start_gzip {
  my $revision = shift;
  my $bindir = shift;

  # Open tarball for reading
  my $tarball = catfile ( $cacheroot, $revision."-".$bindir.".tar" );
  warn "Compressing binary tarball ".$tarball."...\n" if $verbosity > 1;
  open my $tarfh, "<", $tarball
      or die "Could not open ".$tarball.": $!\n";

  # Fork child process
  my $child = fork();
  if ( ! defined $child ) {
    die "Could not fork: $!\n";
  } elsif ( $child ) {
    close $tarfh;
    return;
  }

  # Open temporary file
  my $gzfh = File::Temp->new ( TEMPLATE => "ipxe-gzip-XXXXXX",
			       DIR => $cacheroot );
  chmod 0664, $gzfh or die "Could not set permissions: $!\n";

  # Restore original STDIN, STDOUT and STDERR so that there's
  # somewhere for error messages to go after parent exits
  close STDIN;
  open STDIN, "<&", $origstdin or die "Could not restore STDIN: $!\n";
  close STDOUT;
  open STDOUT, ">&", $origstdout or die "Could not restore STDOUT: $!\n";
  close STDERR;
  open STDERR, ">&", $origstderr or die "Could not restore STDERR: $!\n";

  # Compress to temporary file
  gzip $tarfh => $gzfh or die "Compression failed: ".$GzipError."\n";

  # Obtain cache lock
  cache_lock();

  # Move compressed tarball into position
  my $tarball_gz = $tarball.".gz";
  if ( rename ( $gzfh->filename, $tarball_gz ) ) {
    $gzfh->unlink_on_destroy ( 0 );
  } else {
    # Compressed tarball has already been created concurrently - harmless waste
    warn "Could not create compressed binary tarball ".$tarball_gz.": $!\n";
  }
  undef $gzfh;

  # Create tag in upstream repository.  As before, do this even if
  # compressed tarball already exists.
  my $gztag = "ipxe-build/cached/".$bindir."/".$revision.".gz";
  warn "Creating tag ".$gztag."...\n" if $verbosity > 2;
  systemx ( "git", "--git-dir", $repository, "tag", "--force",
	    $gztag, $revision );

  # Delete tag for uncompressed tarball.  Allow for the tag to have
  # already been deleted, since we released the cache lock while
  # performing compression.
  my $tag = "ipxe-build/cached/".$bindir."/".$revision;
  warn "Deleting tag ".$tag."...\n" if $verbosity > 2;
  eval {
    systemx ( "git", "--git-dir", $repository, "tag", "-d", $tag );
  };
  if ( $@ ) {
    warn "Could not delete tag ".$tag.": $@\n";
  }

  # Delete uncompressed tarball, if it still exists
  unlink ( $tarball );

  # Release cache lock
  cache_unlock();

  # Exit child
  exit ( 0 );
}

###############################################################################
#
# Generate config/local/*.h
#

sub config_local {
  my $worktree = shift;
  my $params = shift;

  # Parse parameters to determine file content
  my $headers = {};
  while ( ( my $key, my $value ) = each %$params ) {
    ( my $header, my $define, my $boolean ) =
	( $key =~ /^(\w+\.h)\/(\w+)(:)?$/ )
	or die "Invalid header/definition pair \"".$key."\"\n";
    next if $value eq "";
    my $line;
    if ( $boolean ) {
      if ( $value ) {
	$line = "#define ".$define;
      } else {
	$line = "#undef ".$define;
      }
    } else {
      if ( $value =~ /^[a-zA-Z0-9_\. ]+$/ ) {
	  $line = "#undef ".$define."\n";
	if ($define =~ /PRODUCT/) {
	  $line .= "#define ".$define." \"".$value."\"";
	} else {
	  $line .= "#define ".$define." ".$value;
	}
      } else {
	die "Invalid definition value \"".$value."\" for \"".$define."\"\n";
      }
    }
    $headers->{$header} ||= "";
    $headers->{$header} .= $line."\n";
  }

  # Generate files
  while ( ( my $header, my $content ) = each %$headers ) {

    warn "Local configuration for ".$header.":\n".$content
	if $verbosity > 1;

    my $file = catfile ( $worktree, "src", "config", "local", $header );
    open my $fh, "+<", $file
	or die "Could not open ".$file.": $!\n";
    print $fh $content;
    close $fh;
  }
}

###############################################################################
#
# Handle files uploaded to be embedded
#

sub embed {
  my $cgi = shift;
  my $params = shift;

  # Retrieve list of uploaded files
  my @files;
  foreach my $param ( sort grep { /^EMBED/ } keys %$params ) {
    ( undef, my $name ) = ( $param =~ /^EMBED(\.(.+))?$/ )
	or die "Invalid EMBED* parameter name \"".$param."\"\n";
    foreach my $value ( $cgi->param ( $param ) ) {
      next unless $value;
      my $tempfile = $cgi->tmpFileName ( $value );
      if ( $tempfile ) {
	# Value is the local filename
	push @files, {
	  name => $value,
	  tempfile => $tempfile,
	};
      } else {
	# Value is the literal content
	push @files, {
	  name => $name,
	  content => $value,
	};
      }
    }
    delete $params->{$param};
  }

  # Do nothing if no files were uploaded
  return unless @files;

  # Create directory for upload symlinks and literal content temporary files
  my $embeddirfh = File::Temp->newdir ( "ipxe-embed-XXXXXX", DIR => $tmpdir,
					CLEANUP => ! $keep );
  my $embeddir = $embeddirfh->dirname;
  warn "Temporary embedded image directory: ".$embeddir."\n" if $verbosity > 0;

  # Create upload symlinks and EMBED list
  my @embed;
  foreach my $file ( @files ) {

    # Determine embedded filename
    ( undef, undef, my $filename ) = splitpath ( $file->{name} );
    die "Invalid embedded image filename \"".$filename."\"\n"
	unless $filename =~ /^\w[\w\.]*$/;
    my $path = catfile ( $embeddir, $filename );
    warn "Embedded image: ".$path."\n" if $verbosity > 0;

    # Create symlink with appropriate name
    if ( $file->{tempfile} ) {
      symlink ( $file->{tempfile}, $path )
	  or die "Could not symlink ".$path." to ".$file->{tempfile}.": $!\n";
    } else {
      warn $file->{content}."\n" if $verbosity > 0;
      open my $fh, ">", $path
	  or die "Could not create ".$path.": $!\n";
      print $fh $file->{content};
      close $fh;
    }

    # Add EMBED list entry
    push @embed, $path;
  }

  return ( join ( ",", @embed ), $embeddirfh );
}

###############################################################################
#
# Handle build request
#

sub build {
  my $cgi = shift;

  # Parse URI
  my $path_info = $cgi->path_info();
  my $params = $cgi->Vars;
  if ( $verbosity > 1 ) {
    warn "Path: ".$path_info."\n";
    warn "Parameters: \n";
    foreach my $key ( sort keys %$params ) {
      warn "  ".$key." = ".$params->{$key}."\n";
    }
  }
  my $segments = [ URI->new ( $path_info )->path_segments ];
  my $binary = ( pop @$segments || $params->{BINARY} );
  delete $params->{BINARY};
  die "No binary specified\n" unless $binary;
  warn "Binary: ".$binary."\n" if $verbosity > 0;
  my $bindir = ( ( scalar @$segments && ( $segments->[-1] =~ /^bin(-|$)/ ) ) ?
		 pop @$segments : ( $params->{BINDIR} || "bin" ) );
  delete $params->{BINDIR};
  warn "Binary directory: ".$bindir."\n" if $verbosity > 0;
  my $revision = ( join ( "/", grep { $_ } @$segments ) ||
		   $params->{REVISION} || "HEAD" );
  delete $params->{REVISION};
  warn "Revision: ".$revision."\n" if $verbosity > 0;

  # Check final binary name
  $binary =~ /^\w[\w-]*\.[a-z]+$/
      or die "Invalid binary name \"".$binary."\"\n";

  # Check binary directory
  die "Invalid binary directory \"".$bindir."\"\n"
      unless exists $bindirs->{$bindir};

  # Check revision
  $revision =~ /^\w\S+$/
      or die "Invalid revision \"".$revision."\"\n";

  # Parse DEBUG, if present
  my $debug = $params->{DEBUG};
  undef $debug if $debug eq "";
  if ( $debug ) {
    die "Invalid DEBUG \"".$debug."\"\n"
	unless $debug =~ /^(\w+(:\d+)?(,\w+(:\d+)?)*)?$/;
    warn "DEBUG: ".$debug."\n" if $verbosity > 0;
  }
  delete $params->{DEBUG};

  # Parse EMBED, if present
  ( my $embed, my $embeddirfh ) = embed ( $cgi, $params );

  # Canonicalise git revision
  warn "Canonicalising revision ".$revision."...\n" if $verbosity > 1;
  $revision = capturex ( "git", "--git-dir", $repository, "rev-parse",
			 "--verify", $revision );
  chomp $revision;
  warn "Canonical revision: ".$revision."\n" if $verbosity > 0;

  # Create temporary directories
  warn "Creating temporary directories...\n" if $verbosity > 1;
  my $gitdirfh = File::Temp->newdir ( "ipxe-build-XXXXXX", DIR => $tmpdir,
				      CLEANUP => ! $keep );
  my $gitdir = $gitdirfh->dirname;
  warn "Temporary git directory: ".$gitdir."\n" if $verbosity > 0;
  my $worktreefh = File::Temp->newdir ( "ipxe-build-XXXXXX", DIR => $tmpdir,
					CLEANUP => ! $keep );
  my $worktree = $worktreefh->dirname;
  warn "Temporary working tree: ".$worktree."\n" if $verbosity > 0;

  # Clone git tree into temporary directory
  warn "Cloning git tree from ".$repository."...\n" if $verbosity > 1;
  systemx ( "git", "clone", "--quiet", "--local", "--shared", "--bare",
	    $repository, $gitdir );

  # Find best cached binary set, if any
  my $cached = load_cached_binaries ( $gitdir, $worktree, $revision, $bindir );

  # Check out git revision into temporary directory
  warn "Checking out revision ".$revision."...\n" if $verbosity > 1;
  systemx ( "git", "--git-dir", $gitdir, "--work-tree", $worktree,
	    "checkout", "--quiet", $revision );

  # Create cached binary set, if necessary
  save_cached_binaries ( $gitdir, $worktree, $revision, $bindir )
      unless ( defined $cached ) && ( $cached eq $revision );

  # Generate config/local/*.h
  config_local ( $worktree, $params );

  # Build final target
  my $target = catfile ( $bindir, $binary );
  warn "Building final target ".$target."...\n" if $verbosity > 1;
  systemx ( "make", "-C", catdir ( $worktree, "src" ),
	    ( $concurrency ? ( "-j", $concurrency ) : () ),
	    ( $debug ? ( "DEBUG=".$debug ) : () ),
	    ( $embed ? ( "EMBEDDED_IMAGE=".$embed ) : () ),
	    $target );

  # Return target
  my $outfile = catfile ( $worktree, "src", $bindir, $binary );
  warn "Returning final target ".$outfile."...\n" if $verbosity > 1;
  open my $outfh, "<", $outfile
      or die "Could not open ".$outfile.": $!\n";
  return ( $outfh, $binary );
}

###############################################################################
#
# Copy data
#
# File::Copy seems to directly use the underlying filehandles, which
# doesn't play nicely with the I/O layer mangling used by FCGI.
#

use constant COPY_BLKSIZE => 4096;

sub copy {
  my $infh = shift;
  my $outfh = shift;

  while ( 1 ) {
    my $len = read ( $infh, my $buffer, COPY_BLKSIZE );
    die "Could not copy data: $!\n" if ! defined $len;
    last unless $len;
    print $outfh $buffer;
  }
}

###############################################################################
#
# Main loop
#

# Avoid zombies
$SIG{CHLD} = "IGNORE";

# Hack around FCGI's apparent inability to cope gracefully with child workers
my $fcgi_destroy_override = Sub::Override->new ( "FCGI::DESTROY", sub {} );

while ( 1 ) {
  my $fcgiin = IO::Handle->new();
  my $fcgiout = IO::Handle->new();
  my $request = FCGI::Request ( $fcgiin, $fcgiout, $fcgiout );
  last if $request->Accept() < 0;

  # Connect up dummy FCGI handle if running from command line
  if ( ! $request->IsFastCGI() ) {
    open $fcgiin, "<&", \*STDIN or die "Could not dup STDIN: $!\n";
    open $fcgiout, ">&", \*STDOUT or die "Could not dup STDOUT: $!\n";
  }

  # Fork child process
  $request->Detach();
  if ( ! $foreground ) {
    my $child = fork();
    if ( ! defined $child ) {
      die "Could not fork: $!\n";
    } elsif ( $child ) {
      next;
    }
  }
  $fcgi_destroy_override->restore();
  $request->Attach();
  $request->LastCall();

  # Create CGI object
  my $cgi;
  {
    # CGI hardcodes the use of STDIN, relying upon the I/O layer
    # mangling used by FCGI to change the definition of STDIN.  Since
    # we need to be able to close and reopen the original STDIN, we
    # tell FCGI to leave STDIN alone and use $fcgiin instead.  We must
    # therefore temporarily redefine STDIN to keep CGI happy.
    local *STDIN = $fcgiin;
    $cgi = CGI->new();
  }

  # Allow system() et al to work normally
  undef $SIG{CHLD};

  # Set niceness
  nice ( $niceness );

  # Redirect STDOUT and STDERR to log file if applicable
  my $logfh = ( -t STDERR ? undef : File::Temp->new() );
  if ( $logfh ) {
    $logfh->autoflush();
    close STDOUT;
    open STDOUT, ">&", $logfh or die "Could not dup logfh for STDOUT: $!\n";
    close STDERR;
    open STDERR, ">&", $logfh or die "Could not dup logfh for STDERR: $!\n";
  }

  # Perform build
  ( my $outfh, my $binary ) = eval { build ( $cgi ) };
  my $build_error = $@;
  warn $build_error if $build_error;

  # Restore STDOUT and STDERR
  close STDOUT;
  open STDOUT, ">&", $origstdout or die "Could not restore STDOUT: $!\n";
  close STDERR;
  open STDERR, ">&", $origstderr or die "Could not restore STDERR: $!\n";

  # Send output to client
  if ( $outfh ) {
    my $stat = stat ( $outfh ) or die "Could not stat ".$binary.": $!\n";
    print $fcgiout $cgi->header ( -status => "200 OK",
				  -type => "application/octet-stream",
				  -attachment => $binary,
				  -Content_Length => $stat->size,
				  -Pragma => "no-cache",
				  -Cache_Control => "no-cache" );
    if ( -t $fcgiout ) {
      warn "<content omitted for tty>\n";
    } else {
      copy ( $outfh, $fcgiout );
    }
  } else {
    print $fcgiout $cgi->header ( -status => "500 Internal server error",
				  -type => "text/plain" );
    print $fcgiout "Build failed:\n\n".$build_error."\n\n";
    if ( $logfh ) {
      print $fcgiout "Build log:\n";
      $logfh->seek ( 0, SEEK_SET );
      copy ( $logfh, $fcgiout );
    }
  }

  # Exit child
  exit ( 0 );
}

