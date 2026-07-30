<?php

declare(strict_types=1);

use App\Core\Migration;

return new class(app()->container->make(\PDO::class)) extends Migration
{
    public function up(): void
    {
        /*
         * Backfill an "Order received" event for existing orders.
         * This is idempotent because it only inserts the event
         * when the order does not already have one.
         */
        $this->db->exec("
            INSERT INTO order_events (
                order_id,
                type,
                title,
                description,
                old_value,
                new_value,
                is_public,
                created_at
            )
            SELECT
                o.id,
                'order_created',
                'Order received',
                'This order existed before order activity tracking was enabled.',
                NULL,
                o.status,
                1,
                COALESCE(o.placed_at, o.created_at, NOW())
            FROM orders o
            WHERE NOT EXISTS (
                SELECT 1
                FROM order_events oe
                WHERE oe.order_id = o.id
                AND oe.type = 'order_created'
            )
        ");

        /*
         * Backfill a fulfillment event for existing orders that already
         * have shipping or tracking information.
         */
        $this->db->exec("
            INSERT INTO order_events (
                order_id,
                type,
                title,
                description,
                old_value,
                new_value,
                is_public,
                created_at
            )
            SELECT
                o.id,
                'fulfillment_update',
                'Fulfillment details updated',
                'Shipping and tracking information was available when timeline tracking was enabled.',
                NULL,
                TRIM(CONCAT(
                    COALESCE(o.shipping_carrier, 'Carrier not set'),
                    ' ',
                    COALESCE(o.tracking_number, '')
                )),
                1,
                COALESCE(o.shipped_at, o.created_at, NOW())
            FROM orders o
            WHERE (
                o.shipping_carrier IS NOT NULL
                OR o.tracking_number IS NOT NULL
                OR o.tracking_url IS NOT NULL
                OR o.shipped_at IS NOT NULL
            )
            AND NOT EXISTS (
                SELECT 1
                FROM order_events oe
                WHERE oe.order_id = o.id
                AND oe.type = 'fulfillment_update'
            )
        ");
    }

    public function down(): void
    {
        $this->db->exec("
            DELETE FROM order_events
            WHERE description IN (
                'This order existed before order activity tracking was enabled.',
                'Shipping and tracking information was available when timeline tracking was enabled.'
            )
        ");
    }
};