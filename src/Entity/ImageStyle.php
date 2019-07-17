<?php

namespace Drupal\idix_crop\Entity;

use Drupal\image\Entity\ImageStyle as ImageStyleBase;
use Drupal\Core\Routing\RequestHelper;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Drupal\Component\Utility\UrlHelper;

class ImageStyle extends ImageStyleBase {

  /**
   * {@inheritdoc}
   */
  public function buildUrl($path, $clean_urls = NULL) {
    $uri = $this->buildUri($path);
    // The token query is added even if the
    // 'image.settings:allow_insecure_derivatives' configuration is TRUE, so
    // that the emitted links remain valid if it is changed back to the default
    // FALSE. However, sites which need to prevent the token query from being
    // emitted at all can additionally set the
    // 'image.settings:suppress_itok_output' configuration to TRUE to achieve
    // that (if both are set, the security token will neither be emitted in the
    // image derivative URL nor checked for in
    // \Drupal\image\ImageStyleInterface::deliver()).
    $token_query = [];
    if (!\Drupal::config('image.settings')->get('suppress_itok_output')) {
      // The passed $path variable can be either a relative path or a full URI.
      $original_uri = file_uri_scheme($path) ? file_stream_wrapper_uri_normalize($path) : file_build_uri($path);
      $token_query = [IMAGE_DERIVATIVE_TOKEN => $this->getPathToken($original_uri)];

      $effects_token = $this->getEffectsToken();
      if(!empty($effects_token)){
        $token_query['etok'] = $effects_token;
      }
    }

    if ($clean_urls === NULL) {
      // Assume clean URLs unless the request tells us otherwise.
      $clean_urls = TRUE;
      try {
        $request = \Drupal::request();
        $clean_urls = RequestHelper::isCleanUrl($request);
      }
      catch (ServiceNotFoundException $e) {
      }
    }

    // If not using clean URLs, the image derivative callback is only available
    // with the script path. If the file does not exist, use Url::fromUri() to
    // ensure that it is included. Once the file exists it's fine to fall back
    // to the actual file path, this avoids bootstrapping PHP once the files are
    // built.
    if ($clean_urls === FALSE && file_uri_scheme($uri) == 'public' && !file_exists($uri)) {
      $directory_path = $this->getStreamWrapperManager()->getViaUri($uri)->getDirectoryPath();
      return Url::fromUri('base:' . $directory_path . '/' . file_uri_target($uri), ['absolute' => TRUE, 'query' => $token_query])->toString();
    }

    $file_url = file_create_url($uri);
    // Append the query string with the token, if necessary.
    if ($token_query) {
      $file_url .= (strpos($file_url, '?') !== FALSE ? '&' : '?') . UrlHelper::buildQuery($token_query);
    }

    return $file_url;
  }

  protected function getEffectsToken(){
    
    return '';
  }

}