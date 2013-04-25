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
$imgpath="./images/";      // directory where to store images
$fname="img";              // image file name without extension
$log=0;                    // debugging / log flag
$loadcam[0]=1;             // set to 1 to retreive image for cam 1
$loadcam[1]=1;             // set to 1 to retreive image for cam 2
$loadcam[2]=1;             // set to 1 to retreive image for cam 3
$loadcam[3]=1;             // set to 1 to retreive image for cam 4
$camflip[0]=1;             // set to 1 to flip image horizontally for cam 1
$camflip[1]=0;             // set to 1 to flip image horizontally for cam 2
$camflip[2]=1;             // set to 1 to flip image horizontally for cam 3
$camflip[3]=0;             // set to 1 to flip image horizontally for cam 4
$thumbs=1;                 // set to 1 to create image thumbnails
$thumbwidth=160;           // width of thumbnail
$thumbheight=120;          // height of thumbnail

// global values
$maxloop=200;                          // max images to read from the stream
$portoffset=14;                        // ofset into jpg for cam port num
$imgfile=$imgpath.$fname;              // image file name
$lockfile=$imgpath."readstream.lock";  // lock file name

//
// start of script
//
if ($log) echo "readstream.php starting\n";

// create the log file
$flock=fopen($lockfile,"w");
fwrite($flock,"Locked for update");
fclose($flock);
if ($log) echo "Lock file created.\n";

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

    // if we have not saved the current cam image then process it
    if ($loadcam[$cpnum]==1) {
      $newfile=$imgfile."-$cport.jpg";
      $tmpfile=$newfile.".tmp";
      $thumbfile=$imgfile."-thumb-$cport.jpg";

      // save image into a temp file
      if ($log) echo "saving image file $tmpfile\n";
      if ($fimg=fopen("$tmpfile","wb")) {
        fwrite($fimg,$frame);
        fclose($fimg);
      }

      // flip the image horizontally if it is marked to be flipped
      if ($camflip[$cpnum]==1) {
        if ($log) echo "Flipping image horizontally.\n";
        exec("convert -flop $tmpfile $tmpfile");
      }

      // move temp file to final image file
      if ($log) echo "Renaming $tmpfile to $newfile\n";
      if (!rename($tmpfile,$newfile)) {
        unlink($newfile);
        rename($tmpfile,$newfile);
      } 

      // create thumbnails if the flag is true
      if ($thumbs==1) {
        if ($log) echo "Creating thumbnail image\n";
        $myimg=imagecreatefromjpeg($newfile);
        $iwidth=imagesx($myimg);
        $iheight=imagesy($myimg);
        $tmpimg=imagecreatetruecolor($thumbwidth,$thumbheight); 
        imagecopyresampled($tmpimg,$myimg,0,0,0,0,
                           $thumbwidth-1,$thumbheight-1,$iwidth,$iheight);
        imagedestroy($myimg);
        imagejpeg($tmpimg,$thumbfile.".tmp");
        if (!rename($thumbfile.".tmp",$thumbfile)) {
            unlink($thumbfile);
            rename($thumbfile.".tmp",$thumbfile);
        } 
      } 

      // mark the camera number as processed  and exit the loop when
      // we have all the images.
      $loadcam[$cpnum]=0;
      if (($loadcam[0]+$loadcam[1]+$loadcam[2]+$loadcam[3])==0) $loop=$maxloop;
    }

    // we need the remainder of the buffer after the second
    // boundary. it contains the start of the next image.
    $r=substr($r,$end+1);    
    if ($log) echo "\n";
  }
}
// close the image stream
fclose($fvid);

// remove the lock file
unlink($lockfile);
if ($log) echo "readstream.php complete\n";
?>
