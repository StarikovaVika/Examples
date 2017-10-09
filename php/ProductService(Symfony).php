<?php

namespace AppBundle\Service;


use AppBundle\Model\CartQuery;
use AppBundle\Model\ItemCart;
use AppBundle\Model\ItemCartQuery;
use Symfony\Component\DependencyInjection\ContainerInterface;
use AppBundle\Model\ProductQuery;

class ProductService
{
    protected $container;

    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }

    public function add($cart_id, $product_id) {
        $product = ProductQuery::create()->findPk($product_id);
        $cart = CartQuery::create()->findPk($cart_id);
        $item = new ItemCart();
        $item
            ->setCart($cart)
            ->setProduct($product)
            ->setCount(1)
            ->save();
        return $item;
    }

    public function delete($item_id) {
        ItemCartQuery::create()
            ->findPk($item_id)
            ->delete();
    }

    public function update($item_id, $count) {
        $item = ItemCartQuery::create()
            ->findPk($item_id)
            ->setCount($count)
            ->save();
        return $item;
    }

    public function clear($cart_id) {
        $cart = CartQuery::create()
            ->findPk($cart_id)
            ->getItemCarts()
            ->delete();
    }
}