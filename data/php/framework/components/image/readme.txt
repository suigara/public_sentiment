This is a modified version of the Image Mod extension.

There's 3 new functions :
 - grayscale
 - emboss
 - negate

The ImageMagick Driver is optimized greatly by appending arguments for convert instead
of reading / saving file for each function. The temp image copy in this driver is now useless
and was removed too.

Author : Parcouss

First release (this is the following) :

说明：
移植自Kohana的Image类库

英文文档地址：http://docs.kohanaphp.com/libraries/image
中文文档地址：http://khnfans.cn/docs/libraries/image

------------------------------------------------------------------------------

使用方法：

Mod::import('system.components.image.Image');//调用系统功能
$image = new Image($imgfile);
$image->resize(400, 400)->quality(75);
$image->render();//$image->save()