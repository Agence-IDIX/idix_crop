<?php

namespace Drupal\idix_crop\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\image\Entity\ImageStyle;
use Drupal\media_entity\Entity\Media;

//use Drupal\media\Entity\Media;

class AdminController extends ControllerBase{

  public function testResize(Media $media){
    $image_styles = ImageStyle::loadMultiple();

    $images = [];

    $images[] = ['#markup' => '<h2>' . $media->name->value . '</h2>'];

    $image = $media->field_upload->entity->getFileUri();
    /**
     * @var  $key
     * @var ImageStyle $style
     */
    foreach($image_styles as $key => $style){
      $style->flush($image);
      $url = $style->buildUrl($image);

      $images[] = [
        '#markup' => '<div><strong>' . $key . '</strong><br/><img src="' . $url . '" /></div>'
      ];
    }

    return $images;

  }

}