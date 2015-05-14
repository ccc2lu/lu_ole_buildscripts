#! /usr/bin/perl

use MIME::Base64;

# Author: Chris Creswell (ccc2@lehigh.edu)
# Updated: 5/7/2014 
# This script fixes some data problems in exports from
# our Sirsi Dynix Symphony system.  It seemed like
# Sirsi's catalogdump program was sometimes shifting
# Marc subfield codes into the subfield values, and putting
# junk data into the subfield code.  This script uses
# regular expressions to fix this issue.  It also
# changes some parts of the leader line to match the
# MARC specification.
# This script also removes invalid XML character references
# that won't validate and replaces them with base64 encoded
# versions of them.  Finally, this script replaces instances 
# of \x{be} with <U+00be}.

# Arguments:
# 0: folder where input and output files live
# 1: MarcXML input file
# 2: prefix for output file names

$workingdir = $ARGV[0];
$prefix = $ARGV[2];

$changes = 0;
$record = "";
open(INPUT, "$workingdir/$ARGV[1]") or die "Unable to open first input file $workingdir/$ARGV[1]";
open(OUTPUT, ">$workingdir/$prefix.$ARGV[1]") or die "Unable to open first output file $workingdir/$prefix.$ARGV[1]";
$line = <INPUT>; # Read off the first line, containing the XML declaration
chomp($line);
$printjustrecord = 0;
print OUTPUT "$line\n";
while ( $line = <INPUT> ) {
    if ( $line =~ m/<record>/ ) {
	if ( $changes > 0 ) {
	    if ( $printjustrecord ) {
		print STDERR "$record\n";
	    } else {
		print STDERR "$changes changes to record:\n$record\n";
	    }
	}
	$changes = 0;
	$record = &readRecord;
    }
    chomp($line);
    # Leader fields ended up with "&#17;", "&#1;", "&#2;"
    # in bytes 22 or 23.  These bytes should always be 0
    # according to http://www.loc.gov/marc/bibliographic/bdleader.html
    # So, we replace them with 0's
    if ( $line =~ /(.*)<leader>(.*)&#[0-9]*;(.*)<\/leader>/ ) {
	print STDERR "Replacing invalid character reference in leader line, before:\n$line\n" unless $printjustrecord;
	$line = "$1<leader>$2" . "0" . "$3</leader>";
	print STDERR "After:\n$line\n" unless $printjustrecord;
	$changes++;
    }
    # Character encoding has been changed to Unicode by marc4j
    # as part of the conversion to MarcXML, but the <leader>
    # lines don't all reflect this yet, so we change the
    # 10th character to "a" in all <leader> lines to fix this.
    # Otherwise, OLE's Marc editor complains.
    if ( $line =~ /(.*)<leader>(.*)<\/leader>/ ) {
	print STDERR "Replacing 10th character of leader field with \"a\" to indicate unicode character encoding, before:\n$line\n" unless $printjustrecord;
	$leader = $2;
	$line = "$1<leader>" . substr($2, 0, 9) . "a" . substr($2, 10) . "</leader>";
	print STDERR "After:\n$line\n" unless $printjustrecord;
	$changes++;
    }
    if ( $line =~ /(.*)<leader>(.*)&([A-Za-z])(.*)<\/leader>/ ) {
	print STDERR "Replacing 10th character of leader field with \"a\" to indicate unicode character encoding, before:\n$line\n" unless $printjustrecord;
	$leader = $2;
	$line = "$1<leader>$2 $3$4<\/leader>/"; 
	print STDERR "After:\n$line\n" unless $printjustrecord;
	$changes++;
    }
    # Replace the "&#31" with the single lower case letter after the ">"
    if ( $line =~ /(.*)<subfield code="&#31;">([a-z])(.*)/ ) {
	print STDERR "Replacing invalid character reference with subfield code, before:\n$line\n" unless $printjustrecord;
	$line = "$1<subfield code=\"$2\">$3";
	print STDERR "After:\n$line\n" unless $printjustrecord;
	$changes++;
    }
    # Remove lines containing a subfield code of "="
    # These were specific to Sirsi, and OLE's Marc editor
    # complains about them since they aren't standard Marc format
    if ( $line =~ /(.*)<subfield code="=">(.*)/ ) {
	print STDERR "Removing subfield with code of \"=\", before:\n$line\n" unless $printjustrecord;
	$line = "";
	print STDERR "After:\n$line\n" unless $printjustrecord;
	$changes++;
    }
    # Base 64 encode the rest, don't know what to do with them
    while ( $line =~ /(.*)(&#[0-9]+;)(.*)/ ) {
	print STDERR "UNKNOWN invalid character data, base 64 encoding it.  Before:\n$line\n" unless $printjustrecord;
	$line = $1 . encode_base64($2, "") . $3;
	print STDERR "After:\n$line\n" unless $printjustrecord;
	$changes++;
    }
    if ( length($line) > 0 ) {
	print OUTPUT "$line\n";
    }
}
close(INPUT);

@files = ("allcallnums.txt", "allcallnumsshelvingkeys.txt", "allcallnumsitemnumbers.txt", "allcallnumsanalytics.txt", "allitems.txt", "boundwiths.txt");
foreach $file (@files) {
    open(INPUT, "$workingdir/$file") or die "Unable to open second input file $workingdir/$file";
    open(OUTPUT, ">$workingdir/$prefix.$file") or die "Unable to open second output file $workingdir/$prefix.$file";
    while( $line = <INPUT> ) {
	chomp($line);
	if ( $line =~ /\x{be}/ ) {
	    print STDERR "Replacing LATIN1 0xbe with <U+00be> in $workingidr/$prefix.$file\n" unless $printjustrecord;
	    $line =~ s/\x{be}/<U+00be>/g;
	}
	print OUTPUT "$line\n";
    }
    close(INPUT);
    close(OUTPUT);
}

# Read to the end of a record, then move
# the file handle pointer back to the 
# beginning of the record again
sub readRecord
{
    my $curpos = tell(INPUT);
    my $record = "";
    my $myline = $line;
    do {
	$record .= $myline;
	$myline = <INPUT>;
	#chomp($line);
    } until ( $myline =~ m/<\/record>/ );
    $record .= $myline;
    seek(INPUT, $curpos, 0);
    return $record;
}
