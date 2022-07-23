<?php

namespace CanGelis\DataModels;

class DataCollection implements \ArrayAccess, \IteratorAggregate, \Countable
{
    /**
     * @var array $items
     */
    protected $items;

    /**
     * JsonCollection constructor.
     *
     * @param array $items
     */
    public function __construct(array $items)
    {
        $this->items = $items;
    }

    /**
     * @inheritDoc
     */
    public function offsetExists($offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     * @inheritDoc
     */
    public function offsetGet($offset): mixed
    {
        return $this->items[$offset];
    }

    /**
     * @inheritDoc
     */
    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset($offset): void
    {
        unset($this->items[$offset]);
    }

    /**
     * @inheritDoc
     */
    public function toJson()
    {
        return json_encode($this->items);
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return array_map(function ($item) {
            if ($item instanceof DataModel) {
                return $item->toArray();
            }
            return $item;
        }, $this->items);
    }

    /**
     * Add an item to the collection.
     *
     * @param \CanGelis\DataModels\DataModel $item
     *
     * @return $this
     */
    public function add(DataModel $item)
    {
        $this->items[] = $item;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Get the first item
     *
     * @param callable|null $callback
     * @param mixed         $default
     *
     * @return mixed
     */
    public function first(callable $callback = null, $default = null): mixed
    {
        if (is_null($callback)) {
            $callback = function ($item) {
                return true;
            };
        }

        foreach ($this->items as $item) {
            if ($callback($item)) {
                return $item;
            }
        }

        return $default;
    }

    /**
     * @inheritDoc
     */
    public function filter(callable $callback): array
    {
        return array_filter($this->items, $callback);
    }
}
