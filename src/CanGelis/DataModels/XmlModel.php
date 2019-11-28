<?php

namespace CanGelis\DataModels;

class XmlModel extends DataModel
{
    /**
     * @var \SimpleXMLElement $data
     */
    protected $data;

    /**
     * Model attributes to be behaved as XML attributes
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * Name of the tag
     *
     * @var string
     */
    protected $root = 'root';

    /**
     * @inheritDoc
     */
    public function __construct(\SimpleXMLElement $data = null, $root = null)
    {
        if (is_null($root)) {
            $root = $this->root;
        }
        if (is_null($data)) {
            $data = new \SimpleXMLElement('<' . $root . '></' . $root . '>');
        }
        $this->data = $data;
    }

    /**
     * Initialize from XML string
     *
     * @param string $data
     * @param string $root
     *
     * @return static
     */
    public static function fromString($data, $root = null)
    {
        return new static(simplexml_load_string($data), $root);
    }

    /**
     * Make an instance from an array
     *
     * @param array  $data
     * @param string $root
     *
     * @return static
     */
    public static function fromArray(array $data, $root = null)
    {
        $instance = new static(null, $root);
        foreach ($data as $key => $value) {
            $instance->{$key} = $value;
        }
        return $instance;
    }

    /**
     * Get the xml element
     *
     * @return \SimpleXMLElement
     */
    public function toXMLElement()
    {
        $xmlElement = clone $this->data;

        // resolve dynamically loaded attribute values
        foreach ($this->attributeValues as $attribute => $value) {
            $value = $this->uncastValue($attribute, $value);
            if (in_array($attribute, $this->attributes)) {
                $xmlElement->addAttribute($attribute, $value);
            } else {
                $xmlElement->addChild($attribute, $value);
            }
        }

        // resolve dynamic has many relations
        foreach ($this->relations as $relationAttribute => $value) {
            list($relationType, $relation) = explode("-", $relationAttribute);
            // has one relationships are already in the data loaded
            if ($relationType == 'hasOne') {
                static::addChild($xmlElement, $value->toXMLElement());
            } else {
                $xmlElement->{$relation} = new \SimpleXMLElement('<' . $relation . '></' . $relation . '>');
                foreach ($value as $xmlModel) {
                    static::addChild($xmlElement->{$relation}, $xmlModel->toXMLElement());
                }
            }
        }

        return $xmlElement;
    }

    /**
     * @inheritDoc
     */
    public function __toString()
    {
        return $this->toXMLElement()->asXML();
    }

    /**
     * Add a child to an xml element
     *
     * @param \SimpleXMLElement $root
     * @param \SimpleXMLElement $child
     */
    public static function addChild(\SimpleXMLElement $root, \SimpleXMLElement $child)
    {
        $node = $root->addChild($child->getName(), (string) $child);
        foreach($child->attributes() as $attr => $value) {
            $node->addAttribute($attr, $value);
        }
        foreach($child->children() as $ch) {
            static::addChild($node, $ch);
        }
    }

    /**
     * @inheritDoc
     */
    protected function resolveHasOneRelationship($relation)
    {
        if (isset($this->data->{$relation})) {
            return new $this->hasOne[$relation]($this->data->{$relation});
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    protected function resolveHasManyRelationship($relation)
    {
        $items = [];
        foreach ($this->data->{$relation}->children() as $child) {
            $items[] = new $this->hasMany[$relation]($child);
        }

        return $this->makeCollection($items);
    }

    /**
     * @inheritDoc
     */
    protected function setHasOne($relation, $value)
    {
        $relatedClass = $this->hasOne[$relation];
        if (is_array($value)) {
            return $relatedClass::fromArray($value, $relation);
        }

        if ($value instanceof XmlModel) {
            return $value;
        }

        if ($value instanceof \SimpleXMLElement) {
            return new $relatedClass($value, $relation);
        }
    }

    /**
     * @inheritDoc
     */
    protected function setHasMany($relation, $values)
    {
        unset($this->data->{$relation});
        $collection = $this->makeCollection([]);

        foreach ($values as $value) {
            $class = $this->hasMany[$relation];

            if (is_array($value)) {
                $collection->add($class::fromArray($value));
            }

            if ($value instanceof XmlModel) {
                $collection->add($value);
            }

            if ($value instanceof \SimpleXMLElement) {
                $collection->add(new $class($value));
            }
        }

        return $collection;
    }

    /**
     * Get the attribute
     *
     * @param $attribute
     *
     * @return mixed|\SimpleXMLElement
     */
    protected function getAttribute($attribute)
    {
        if (!array_key_exists($attribute, $this->attributes)) {
            return isset($this->data->{$attribute}) ? (string)$this->data->{$attribute} : null;
        }

        foreach ($this->data->attributes() as $key => $value) {
            if ($key == $attribute) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    protected function hasAttribute($attribute)
    {
        if (!array_key_exists($attribute, $this->attributes)) {
            return isset($this->data->{$attribute});
        }

        foreach ($this->data->attributes() as $key => $value) {
            if ($key == $attribute) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    protected function onLoadAttribute($attribute)
    {
        unset($this->data->{$attribute});
    }

    /**
     * @inheritDoc
     */
    public function __unset($name)
    {
        unset($this->data->{$name});
        unset($this->relations[$name]);
        unset($this->attributeValues[$name]);
    }
}
