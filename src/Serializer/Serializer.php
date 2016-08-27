<?php namespace Talonon\Jsonizer\Encoder;

use Talonon\Jsonizer\BaseCoder;
use Talonon\Jsonizer\JsonizedResponse;
use Talonon\Jsonizer\MapsOutput;
use Illuminate\Contracts\Support\Arrayable;
use Kps3\Framework\Exceptions\EntityNotFoundException;

class Encoder extends BaseCoder {

  public function __construct($mappers, $allowedRelationships = null) {
    parent::__construct($mappers);

    if (!is_null($allowedRelationships) && !is_array($allowedRelationships)) {
      $allowedRelationships = explode(',', $allowedRelationships);
    }
    $this->_allowedRelationships = $allowedRelationships;
  }

  private $_allowedRelationships = null;
  private $_meta = ['can-include' => []];
  private $_pagination = null;

  public function Encode(&$data) {
    $includeMeta = \Input::get('meta', false);
    if ($data instanceof JsonizedResponse) {
      $result = $data;
      $this->_meta = $data->GetIncludeDefaultMeta() ? array_merge($this->_meta, $data->GetMeta()) : $data->GetMeta();
      $includeMeta = $data->GetIncludeMeta();
      $data = $data->GetData();
    } else {
      $result = new JsonizedResponse();
    }
    $this->_encodeData($data);
    return $result->SetData($data)
                  ->SetMeta($this->_meta)
                  ->SetPagination($this->_pagination)
                  ->SetIncludeMeta($includeMeta);
  }

  private function _encodeItem(&$data) {
    if (is_scalar($data) || is_null($data) || empty($data)) {
      return;
    } else if ($data instanceof \Closure) {
      try {
        $result = $data();
      }
      catch (EntityNotFoundException $nfex) {
        return null;
      }
    } else {
      $mapper = $this->getMapper(get_class($data));
      if (!$mapper) {
        $data = null;
        return;
      }
      $result = $mapper->GetAttributes($data);
      $result = is_array($result) ? array_merge($result, $this->_getRelatedItems($data, $mapper)) : $result;
    }
    $this->_encodeData($result);
    $data = $result;
  }

  private function _encodeData(&$data) {
    if (is_array($data) || $data instanceof \ArrayAccess) {
      if ($data instanceof Arrayable) {
        $data = $data->toArray();
      }
      foreach ($data as $key => &$result) {
        $this->_encodeData($data[$key]);
      }
    } else {
      $this->_encodeItem($data);
    }
  }


  private function _getRelatedItems(&$data, MapsOutput $mapper) {

    $relationships = $mapper->GetRelationships($data);
    $prefix = $mapper->GetResourceType() . '.';
    foreach ($relationships as $key => $related) {
      $includeKey = $prefix . $key;
      !in_array($includeKey, $this->_meta['can-include']) && array_push($this->_meta['can-include'], $includeKey);

      if (is_array($this->_allowedRelationships)) {
        if ($mapper->GetRemovedIncludes()) {
          $this->_allowedRelationships = array_diff($this->_allowedRelationships, $mapper->GetRemovedIncludes());
        }
        if (!in_array($includeKey, $this->_allowedRelationships)) {
          unset($relationships[$key]);
        }
      }
    }
    return $relationships;
  }

}
