<?php

namespace Drupal\idix_crop\Plugin\ImageEffect;

use Drupal\Core\Image\ImageInterface;
use Drupal\crop\Entity\CropType;
use Drupal\crop\Plugin\ImageEffect\CropEffect;

/**
 * Crops an image resource.
 *
 * @ImageEffect(
 *   id = "idix_crop_crop",
 *   label = @Translation("Manual crop IDIX"),
 *   description = @Translation("Applies manually provided crop to the image, or fallback to a default one.")
 * )
 */
class IdixCropEffect extends CropEffect {

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    if (empty($this->configuration['crop_type']) || !$this->typeStorage->load($this->configuration['crop_type'])) {
      $this->logger->error('Manual image crop failed due to misconfigured crop type on %path.', ['%path' => $image->getSource()]);
      return FALSE;
    }

    if ($crop = $this->getCrop($image)) {
      $anchor = $crop->anchor();
      $size = $crop->size();

      if (!$image->crop($anchor['x'], $anchor['y'], $size['width'], $size['height'])) {
        $this->logger->error('Manual image crop failed using the %toolkit toolkit on %path (%mimetype, %width x %height)', [
            '%toolkit' => $image->getToolkitId(),
            '%path' => $image->getSource(),
            '%mimetype' => $image->getMimeType(),
            '%width' => $image->getWidth(),
            '%height' => $image->getHeight(),
          ]
        );
        return FALSE;
      }
    }else{
      /** @var CropType $cropConf */
      $cropConf = $this->typeStorage->load($this->configuration['crop_type']);

      $size = $this->_getDefaultSize($image, $cropConf);
      if($size['width'] == null || $size['height'] == null){
        return true;
      }
      if (!$image->crop($size['x'], $size['y'], $size['width'], $size['height'])) {
        $this->logger->error('Manual image crop failed using the %toolkit toolkit on %path (%mimetype, %width x %height)', [
            '%toolkit' => $image->getToolkitId(),
            '%path' => $image->getSource(),
            '%mimetype' => $image->getMimeType(),
            '%width' => $image->getWidth(),
            '%height' => $image->getHeight(),
          ]
        );
        return FALSE;
      }

    }

    return TRUE;
  }

  protected function _getDefaultSize(ImageInterface $image, CropType $cropConf){
    $size = [
      'width' => null,
      'height' => null,
      'x' => 0,
      'y' => 0,
    ];

    $aspect_ratio = $cropConf->getAspectRatio();
    $ratio = $this->_parseRatio($aspect_ratio);

    $originalWidth = $image->getWidth();
    $originalHeight = $image->getHeight();
    $originalRatio = $this->_parseRatio($originalWidth . ':' . $originalHeight);


    if($ratio == $originalRatio){
      $size['width'] = $originalWidth;
      $size['height'] = $originalHeight;
    }
    elseif($ratio > $originalRatio){
      $size['width'] = $originalWidth;
      $size['height'] = floor($originalWidth / $ratio);
      $size['y'] = floor(($originalHeight / 2) - ($size['height'] / 2));
    }else{
      $size['height'] = $originalHeight;
      $size['width'] = floor($originalHeight * $ratio);
      $size['x'] = floor(($originalWidth / 2) - ($size['width'] / 2));
    }

    return $size;
  }

  protected function _parseRatio($aspect_ratio){
    if(strpos($aspect_ratio, ':') !== false){
      $parts = explode(':', $aspect_ratio);
      $num1 = intval($parts[0], 10);
      $num2 = intval($parts[1], 10);
      return $num1 / $num2;
    }
    return floatval($aspect_ratio);
  }

}