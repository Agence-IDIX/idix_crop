<?php

namespace Drupal\idix_crop\Plugin\ImageEffect;

use Drupal\Core\Image\ImageInterface;
use Drupal\crop\CropInterface;
use Drupal\crop\Entity\CropType;
use Drupal\crop\Plugin\ImageEffect\CropEffect;
use Drupal\crop\Entity\Crop;

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
    $ratio_data = [];
    $ratio = $this->_parseRatio($aspect_ratio, $ratio_data);

    $originalWidth = $image->getWidth() != null ? $image->getWidth() : $ratio_data['width'];
    $originalHeight = $image->getHeight() != null ? $image->getHeight() : $ratio_data['height'];
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

  protected function _parseRatio($aspect_ratio, &$ratio_data = []){
    if(strpos($aspect_ratio, ':') !== false){
      $parts = explode(':', $aspect_ratio);
      $num1 = intval($parts[0], 10);
      $num2 = intval($parts[1], 10);
      $ratio_data['width'] = $num1;
      $ratio_data['height'] = $num2;
      return $num1 / $num2;
    }

    $value = floatval($aspect_ratio);
    $ratio_data['width'] = $value;
    $ratio_data['height'] = $value;

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function transformDimensions(array &$dimensions, $uri) {
    $crop = Crop::findCrop($uri, $this->configuration['crop_type']);
    if (!$crop instanceof CropInterface) {
      $image = $this->imageFactory->get($uri);
      /** @var CropType $cropConf */
      $cropConf = $this->typeStorage->load($this->configuration['crop_type']);
      $size = $this->_getDefaultSize($image, $cropConf);

      $dimensions['width'] = $size['width'];
      $dimensions['height'] = $size['height'];
      return;
    }
    $size = $crop->size();

    // The new image will have the exact dimensions defined for the crop effect.
    $dimensions['width'] = $size['width'];
    $dimensions['height'] = $size['height'];
  }

}