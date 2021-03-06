<?php
class UNL_ENews_File_Image extends UNL_ENews_File
{
    const THUMB_WIDTH    = 256;
    const THUMB_HEIGHT   = 192;

    const FULL_AD_WIDTH  = 536;
    const FULL_AD_HEIGHT = 96;

    const HALF_AD_WIDTH  = 253;
    const HALF_AD_HEIGHT = 96;

    const MAX_WIDTH  = 556;
    const HALF_WIDTH = 273;

    const GRID2_WIDTH  = 140;
    const GRID3_WIDTH  = 222;
    const GRID4_WIDTH  = 304;
    const GRID5_WIDTH  = 386;
    const GRID6_WIDTH  = 468;
    const GRID7_WIDTH  = 550;
    const GRID8_WIDTH  = 632;
    const GRID9_WIDTH  = 714;
    const GRID10_WIDTH = 796;
    const GRID11_WIDTH = 878;
    const GRID12_WIDTH = 960;

    // Max image size displayed to the user
    const THUMBNAIL_SELECTION_WIDTH = 410;

    /**
     * Save a thumbnail and return the object
     *
     * Sample:
     * ------------------------------
     * |                            |
     * |      y1----------          |
     * |      |          |          |
     * |      |          |          |
     * |    x1,y2--------x2         |
     * |-----------------------------
     *
     * @param int $x1  X coordinate offset start
     * @param int $x2  X coordinate offset end
     * @param int $y1  Y coordinate offset start
     * @param int $y2  Y coordinate offset end
     * @param int $width  Final image width
     * @param int $height Final image height
     *
     * @return UNL_ENews_File_Image
     */
    function saveThumbnail($x1=0, $x2=0, $y1=0, $y2=0)
    {
        // Determine orientation
        if ($x2 - $x1 > $y2 - $y1) {
            $width=self::THUMB_WIDTH;
            $height=self::THUMB_HEIGHT;
        } else {
            $width=self::THUMB_HEIGHT;
            $height=self::THUMB_WIDTH;
        }

        // Crop the image ***************************************************************
        // Get dimensions of the original image
        list($current_width, $current_height) = $this->getSize();

        if ($x1 < 0) {
            // User did not select a cropping area
            $x1 = 0;
            $y1 = 0;
            $x2 = $current_width;
            $y2 = $current_width*($height/$width);
        } else {
            // Needs to be adjusted to account for the scaled down 410px-width size that's displayed to the user
            if ($current_width > self::THUMBNAIL_SELECTION_WIDTH) {
                $x1 = ($current_width/self::THUMBNAIL_SELECTION_WIDTH)*$x1;
                $y1 = ($current_height/(self::THUMBNAIL_SELECTION_WIDTH*$current_height/$current_width))*$y1;
                $x2 = ($current_width/self::THUMBNAIL_SELECTION_WIDTH)*$x2;
                $y2 = ($current_height/(self::THUMBNAIL_SELECTION_WIDTH*$current_height/$current_width))*$y2;
            }
        }

        if ($thumb = $this->resizeImage($x1, $x2, $y1, $y2, $width, $height)) {
            $thumb->use_for = 'thumbnail';
            $thumb->save();
            return $thumb;
        }

        return false;
    }

    function saveMaxWidth()
    {
        return $this->saveVarWidth(self::MAX_WIDTH);
    }

    function saveHalfWidth()
    {
        return $this->saveVarWidth(self::HALF_WIDTH);
    }

    function __call($method, $args)
    {
        if (preg_match('/save([\w]+)Width/', $method, $matches)) {
            $reflection = new ReflectionClass(__CLASS__);
            $valid_sizes = $reflection->getConstants();
            if (array_key_exists(strtoupper($matches[1].'_WIDTH'), $valid_sizes)) {
                return $this->saveVarWidth($valid_sizes[strtoupper($matches[1].'_WIDTH')]);
            }
            $size = array_search($matches[1], $valid_sizes);
            if (false !== strpos($size, '_WIDTH')) {
                return $this->saveVarWidth($matches[1]);
            }
            throw new InvalidArgumentException('I cannot create that size image for you', 400);
            
        }
        throw new RuntimeException('Invalid method call', 500);
    }

    protected function saveVarWidth($width)
    {
        list($current_width, $current_height) = $this->getSize();

        $new_height = $width/$current_width * $current_height;
        if ($thumb = $this->resizeImage(0, $current_width, 0, $current_height, $width, $new_height)) {
            $thumb->use_for = $width.'_wide';
            $thumb->save();
            return $thumb;
        }

        return false;
    }

    function resizeImage($x1=0, $x2=0, $y1=0, $y2=0, $width, $height)
    {
        $file = 'data://'.$this->type.';base64,' . base64_encode($this->data);
        list($current_width, $current_height) = getimagesize($file);
        // This will be the final size of the cropped image
        $crop_width  = $x2-$x1;
        $crop_height = $y2-$y1;

        // Resample the image
        $croppedimage = imagecreatetruecolor($crop_width, $crop_height);
        switch ($this->type) {
            case 'image/jpeg':
            case 'image/pjpeg':
                $create_method = 'imagecreatefromjpeg';
                $output_method = 'imagejpeg';
                break;
            case 'image/png':
            case 'image/x-png':
                $create_method = 'imagecreatefrompng';
                $output_method = 'imagepng';
                break;
            case 'image/gif':
                $create_method = 'imagecreatefromgif';
                $output_method = 'imagegif';
                break;
            default:
                throw new Exception('I do not know how to resize a file of that content type: '.$this->type, 501);
        }
        $current_image = $create_method($file);

        imagecopy($croppedimage, $current_image, 0, 0, $x1, $y1, $current_width, $current_height);

        // Resize the image ************************************************************
        $current_width = $crop_width;
        $current_height = $crop_height;
        $canvas = imagecreatetruecolor($width, $height);
        imagecopyresampled($canvas, $croppedimage, 0, 0, 0, 0, $width, $height, $current_width, $current_height);

        ob_start();
        $output_method($canvas);
        imagedestroy($canvas);

        $resized              = new self();
        $resized->name        = $this->name;
        $resized->type        = $this->type;
        $resized->description = $this->description;
        $resized->size        = ob_get_length();
        $resized->data        = ob_get_clean();


        // Save the thumbnail **********************************************************
        if ($resized->save()) {
            return $resized;
        }

        return false;
    }

    function getSize()
    {
        $file = 'data://'.$this->type.';base64,' . base64_encode($this->data);
        return getimagesize($file);
    }
}
