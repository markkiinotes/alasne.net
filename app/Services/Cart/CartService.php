<?php

declare(strict_types=1);

namespace App\Services\Cart;

use RuntimeException;

class CartService
{
    private const SESSION_KEY = 'storefront_carts';

    public function items(int $storeId): array
    {
        return $_SESSION[self::SESSION_KEY][$storeId] ?? [];
    }

    public function quantityFor(int $storeId, int $productId): int
    {
        return (int) ($_SESSION[self::SESSION_KEY][$storeId][$productId] ?? 0);
    }

    public function add(int $storeId, int $productId, int $quantity, int $availableInventory): void
    {
        if ($availableInventory <= 0) {
            throw new RuntimeException('This product is currently out of stock.');
        }

        $quantity = max(1, $quantity);

        $currentQuantity = $this->quantityFor($storeId, $productId);
        $newQuantity = $currentQuantity + $quantity;

        if ($newQuantity > $availableInventory) {
            throw new RuntimeException('Quantity exceeds available inventory.');
        }

        $_SESSION[self::SESSION_KEY][$storeId][$productId] = $newQuantity;
    }

    public function update(int $storeId, int $productId, int $quantity, int $availableInventory): void
    {
        if ($quantity <= 0) {
            $this->remove($storeId, $productId);
            return;
        }

        if ($availableInventory <= 0) {
            $this->remove($storeId, $productId);
            throw new RuntimeException('This product is currently out of stock.');
        }

        if ($quantity > $availableInventory) {
            throw new RuntimeException('Quantity exceeds available inventory.');
        }

        $_SESSION[self::SESSION_KEY][$storeId][$productId] = $quantity;
    }

    public function remove(int $storeId, int $productId): void
    {
        unset($_SESSION[self::SESSION_KEY][$storeId][$productId]);

        if (empty($_SESSION[self::SESSION_KEY][$storeId])) {
            unset($_SESSION[self::SESSION_KEY][$storeId]);
        }
    }

    public function clear(int $storeId): void
    {
        unset($_SESSION[self::SESSION_KEY][$storeId]);
    }

    public function totalQuantity(int $storeId): int
    {
        return array_sum($this->items($storeId));
    }
}