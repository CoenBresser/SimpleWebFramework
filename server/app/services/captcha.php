<?php
session_start();

// Create the image
$word_1 = '';
for ($i = 0; $i < 4; $i++) {
  $word_1 .= chr(rand(97, 122));
}

$word_2 = '';
for ($i = 0; $i < 4; $i++) {
  $word_2 .= chr(rand(97, 122));
}

$_SESSION['random_number'] = $word_1.' '.$word_2;

$image = imagecreatetruecolor(150, 40);
$color = imagecolorallocate($image, 255, 255, 255);// color
$backgroundColor = imagecolorallocatealpha($image, 0, 0, 0, 127); // background color white
imagefilledrectangle($image, 0,0, 709, 99, $backgroundColor);
imagettftext ($image, 18, 0, 5, 30, $color, "recaptchaFont.ttf", $_SESSION['random_number']);

header("Content-type: image/png");
imagepng($image);

?>