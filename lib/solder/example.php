<?php

require_once('Solder.php');

$templ_en = new Solder('example.solder', 'en');
$templ_fr = new Solder('example.solder', 'fr');

print "\nOne - English\n";
print $templ_en->fuse('test_one');

print "\n\nOne - French\n";
print $templ_fr->fuse('test_one');

print "\n\nTwo\n";
print $templ_en->fuse('test_two', array(
   'explanation' => 'Not to be pronounced as "teegr"',
   'animal' => 'tiger',
   'appendix' => 'toe'
));

print "\n\nThree\n";
print $templ_en->fuse('test_three', array('data' => "Single quote: ' Double quote: \" Newline: \n UTF8: ő Angle bracket: >"));

print "\n\n";

?>