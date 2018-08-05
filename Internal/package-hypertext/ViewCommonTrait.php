<?php namespace ZN\Hypertext;
/**
 * ZN PHP Web Framework
 * 
 * "Simplicity is the ultimate sophistication." ~ Da Vinci
 * 
 * @package ZN
 * @license MIT [http://opensource.org/licenses/MIT]
 * @author  Ozan UYKUN [ozan@znframework.com]
 */

use ZN\Base;
use ZN\Classes;
use ZN\Datatype;
use ZN\Inclusion;
use ZN\Authorization;
use ZN\DataTypes\Arrays;
use ZN\Hypertext\Exception\PermissionRoleIdException;

trait ViewCommonTrait
{
    use FormElementsTrait, HtmlElementsTrait;

    /**
     * Keeps settings
     * 
     * @var array
     */
    protected $settings = [];

    /**
     * Keeps use elements
     * 
     * @var array
     */
    protected $useElements =
    [
        'addclass' => 'class'
    ];

    /**
     * Magic Call
     * 
     * @param string $method
     * @param array  $parameters
     * 
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $realMethod = $method;
        $method     = strtolower($method);
        $className  = Classes::onlyName(__CLASS__);

        if( $className === 'Html')
        {
            $multiElement = $this->elements['multiElement'];

            # Multiple Element
            if( array_key_exists($method, $multiElement) )
            {
                $realMethod = $multiElement[$method];

                return $this->_multiElement($realMethod, ...$parameters);
            }
            elseif( in_array($method, $multiElement) )
            {
                return $this->_multiElement($realMethod, ...$parameters);
            }

            # Single Element
            elseif( in_array($method, $this->elements['singleElement']) )
            {
                return $this->_singleElement($realMethod, ...$parameters);
            }

            # Media Content
            elseif( in_array($method, $this->elements['mediaContent']) )
            {
                return $this->_mediaContent($parameters[0], $parameters[1] ?? NULL, $parameters[2] ?? [], $realMethod);
            }

            # Media
            elseif( in_array($method, $this->elements['media']) )
            {
                return $this->_media($parameters[0], $parameters[1] ?? [], $realMethod);
            }

            # Content Attribute
            elseif( in_array($method, $this->elements['contentAttribute']) )
            {
                return $this->_contentAttribute($parameters[0], $parameters[1] ?? [], $realMethod);
            }

            # Content
            elseif( in_array($method, $this->elements['content']) )
            {
                return $this->_content($parameters[0], $realMethod);
            }
        }
        elseif( $className === 'Form' )
        {
            if( in_array($method, $this->elements['input']) )
            {
                return $this->_input($parameters[0] ?? '', $parameters[1] ?? '', $parameters[2] ?? [], $realMethod);
            }
        }

        if( empty($parameters) )
        {
            $parameters[0] = $method;
        }
        else
        {
            if( $parameters[0] === false )
            {
                return $this;
            }

            if( $parameters[0] === true )
            {
                $parameters[0] = $method;
            }
        }

        if( isset($this->useElements[$method]) )
        {
            $method = $this->useElements[$method];
        }

        # Convert exampleData to example-data [4.6.1]
        if( ! ctype_lower($realMethod) )
        {
            $newMethod = NULL;
            $split     = Datatype::splitUpperCase($realMethod);
            $method    = implode('-', Arrays\Casing::lower($split));
        }

        $this->_element($method, ...$parameters);

        return $this;
    }

    /**
     * Sets attributes
     * 
     * @param array $attributes
     * 
     * @return string
     */
    public function attributes(Array $attributes) : String
    {
        unset($this->settings['attr']['perm']);

        $attribute = '';

        if( ! empty($this->settings['attr']) )
        {
            $attributes = array_merge($attributes, $this->settings['attr']);

            $this->settings['attr'] = [];
        }

        foreach( $attributes as $key => $values )
        {
            if( is_numeric($key) )
            {
                $attribute .= ' '.$values;
            }
            else
            {
                if( ! empty($key) )
                {
                    $attribute .= ' '.$key.'="'.$values.'"';
                }
            }
        }

        return $attribute;
    }

    /**
     * Get input 
     * 
     * @param string $type       = NULL
     * @param string $name       = NULL
     * @param string $value      = NULL
     * @param array  $attributes = []
     * 
     * @return string
     */
    public function input(String $type = NULL, String $name = NULL, String $value = NULL, Array $attributes = []) : String
    {
        if( isset($this->settings['attr']['type']) )
        {
            $type = $this->settings['attr']['type'];

            unset($this->settings['attr']['type']);
        }

        $this->settings['attr'] = [];

        return $this->_input($name, $value, $attributes, $type);
    }

    /**
     * Open modal
     * 
     * @param string $selector
     * 
     * @return this
     */
    public function modal(String $selector)
    {
        $this->settings['attr']['data-toggle'] = 'modal';
        $this->settings['attr']['data-target'] = $selector;

        return $this;
    }

    /**
     * Generate modal box
     * 
     * @param string $id
     * @param array  $data
     * 
     * @return string
     */
    public function modalbox(String $id, Array $data = [])
    {
        $data = 
        [
            'modalId'             => $id,
            'modalHeader'         => $this->settings['attr']['modal-header'         ] ?? NULL,
            'modalBody'           => $this->settings['attr']['modal-body'           ] ?? NULL,
            'modalFooter'         => $this->settings['attr']['modal-footer'         ] ?? NULL,
            'modalDissmissButton' => $this->settings['attr']['modal-dissmiss-button'] ?? NULL
        ];

        $this->settings['attr'] = [];

        return $this->getModalResource('standart', $data);
    }

    /**
     * Serilize form into controller
     * 
     * @param string          $url
     * @param string|callable $selector
     * @param string|array    $datatype|$properties
     */
    public function serializer(String $url, $selector = '.modal-body', $datatype = 'standart')
    {
        $selector = is_string($selector)
                  ? ($this->settings['attr']['data-target'] ?? NULL) . Base::prefix($selector, ' ')
                  : $selector;

        $this->convertSerializerDataType($datatype);

        $data = 
        [
            'serializerUrl'       => $url,
            'serializerSelector'  => $selector,
            'serializerFunction'  => $function = 'serializer' . md5(uniqid()),
            'serializerProperties'=> $this->transferAttributesAndUnset('serializer', 'properties')
        ];

        $this->settings['attr']['onclick'] = $function . '(this)';

        echo $this->getAjaxResource('serializer', $data);

        return $this;
    }

    /**
     * Protected convert serializeer data type
     */
    protected function convertSerializerDataType($datatype)
    {
        if( $datatype === 'json' )
        {
            $this->settings['serializer']['properties'] = 'dataType:"json",' . EOL;
        }
        elseif( is_array($datatype) )
        {
            $this->settings['serializer']['properties'] = rtrim(ltrim(json_encode($datatype), '{'), '}') . ',' . PHP_EOL;
        }
    }

    /**
     * Protected transfer attributes and unset
     */
    protected function transferAttributesAndUnset($type, $attr)
    {
        $return = $this->settings[$type][$attr] ?? NULL;

        unset($this->settings[$type][$attr]);

        return $return;
    }

    /**
     * Protected get modal resource
     */
    protected function getModalResource(String $resources = 'standart', $data, $directory = 'Modals')
    {
        return Inclusion\View::use($resources, $data, true, __DIR__ . '/Resources/' . $directory . '/');
    }

    /**
     * Protected get modal resource
     */
    protected function getAjaxResource(String $resources = 'serializer', $data)
    {
        return $this->getModalResource($resources, $data, 'Ajax');
    }

    /**
     * Protected Input
     */
    protected function _input($name = '', $value = '', $attributes = [], $type = '')
    {
        if( $name !== '' )
        {
            $attributes['name'] = $name;
        }

        if( $value !== '' )
        {
            $attributes['value'] = $value;
        }

        if( ! empty($attributes['name']) )
        {
            $this->_postback($attributes['name'], $attributes['value'], $type);

            # 5.8.2.8[added]
            $this->getVMethodMessages();

            # 5.4.2[added]
            $this->_validate($attributes['name'], $attributes['name']);

            # 5.4.2[added]
            $this->_getrow($type, $value, $attributes);
        }

        $perm   = $this->settings['attr']['perm'] ?? NULL;
        
        $return = '<input type="'.$type.'"'.$this->attributes($attributes).'>'.EOL;

        return $this->_perm($perm, $return);
    }

    /**
     * Protected Perm [5.4.5]
     */
    protected function _perm($perm, $return)
    {
        if( $perm !== NULL )
        {
            if( Authorization\PermissionExtends::$roleId === NULL )
            {
                throw new PermissionRoleIdException();
            }

            return Authorization\Process::use($perm, $return);
        }

        return $return;
    }

    /**
     * Protected Element
     */
    protected function _element($function, $element)
    {
        $this->settings['attr'][strtolower($function)] = $element;
    }
}
