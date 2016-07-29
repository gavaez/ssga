<?php

namespace GA;

/**
 * GA productFieldObject struct
 *
 * @link https://developers.google.com/analytics/devguides/collection/analyticsjs/enhanced-ecommerce#product-data
 */
final class productFieldObject
{
    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $brand;

    /**
     * @var string
     */
    public $category;

    /**
     * @var string
     */
    public $variant;

    /**
     * @var float
     */
    public $price;

    /**
     * @var int
     */
    public $quantity;

    /**
     * @var int
     */
    public $position;

    /**
     * @param mixed[] $attributes
     */
    public function __construct(array $attributes = [])
    {
        foreach ($attributes as $attr => $value) {
            if (property_exists($this, $attr)) {
                $this->$attr = $value;
            }
        }
    }
}
