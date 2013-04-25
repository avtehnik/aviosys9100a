 <?
// readstream.php
//
// by Richard Camp
// rcamp at campworld dot net
// Copyright 2006
// All rights reserved
//
// Please make a donation. Even $1. This is an example script.
// There is no warranty. Use at your own risk.
// NOT FOR COMERCIAL USE. Personal use is fine.
//
// INTRODUCTION
// This script parses the stream form a IP Camera 9100 (A) for jpgs.
// Set the camera server for round robbin mode and all 4 inputs.
// Include the script in your script to generate the files.
//   ex.  include('readstream.php')
//
// User provided parameters
$camurl="http://192.168.1.3/GetData.cgi";
$imgpath="./images/";             // directory where to store images
$fname="img";              // image file name without extension
$log=1;                    // debugging / log flag
$maxcams=4;                // max cams 1-4

// global values
$maxloop=200;               // max images to read from the stream
$portoffset=14;            // ofset into jpg for cam port num
$imgfile=$imgpath.$fname;  // image file name
$camnum=0;                 // camera number

//
// start of script
//
if ($log) echo "readstream.php starting\n";

// open the stream to the video server
if ($log) echo "opening stream $camurl\n";
$fvid=fopen($camurl,"r");
if (!$fvid) {
  // cannot open mjpeg stream
  if ($log) echo "cannot open stream $camurl\n";
} else {
  // We are connected so start reading data
  if ($log) echo "connected to $camurl\n";
  $r='';

  // read a number of images from the stream and 
  // save them to files
  for ($loop=1; $loop<=$maxloop; $loop++) {

    // read the stream until 2 boundaries are found
    // 
    if ($log) echo "reading data\n";
    while (substr_count($r,"--WIN")<2) $r.=fread($fvid,256);

    // get the start and end offsets for the jpg
    // and extract the image
    if ($log) echo "extracting jpeg\n";
    $start = strpos($r,"Content-Type: image/jpeg")+28;
    $end   = strpos($r,"--WIN",$start);
    $frame = substr($r,$start,$end - $start);

    // get the camera port the image belongs to
    $cport=bin2hex($frame[$portoffset]);
    $cpnum=ord($frame[$portoffset]);
    if ($log) echo "image is for camera port $cport hex $cport\n";

    if (($camnum==$cpnum)&&($camnum<$maxcams)) {
      // save the image file
      if (file_exists("$imgfile-$cport.jpg")) {
        if ($log) echo "removing old file\n";
        unlink("$imgfile-$cport.jpg");
      }
      if ($log) echo "saving image file $imgfile-$cport.jpg\n";
	  
	  file_put_contents("$imgfile-$cport.jpg" ,$frame); 
	  
      $camnum++;
      if ($camnum==$maxcams) $loop=$maxloop;
    }

    // we need the remainder of the buffer after the second
    // boundary. it contains the start of the next image.
    $r=substr($r,$end+1);    
    if ($log) echo "\n";
  }
}
fclose($fvid);
if ($log) echo "readstream.php complete\n";
?> 