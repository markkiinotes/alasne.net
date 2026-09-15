<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        /*
         * Find the latest successful charge for each order and
         * synchronize the order payment snapshot and totals.
         *
         * This repairs existing affected orders and is safe for
         * orders that are already correct.
         */
        $this->db->exec("
            UPDATE orders AS o
            INNER JOIN (
                SELECT
                    pt.id,
                    pt.order_id,
                    pt.payment_method_id,
                    pt.payment_method_name,
                    pt.payment_method_code,
                    pt.provider,
                    pt.currency,
                    pt.amount,
                    pt.processed_at
                FROM payment_transactions AS pt
                INNER JOIN (
                    SELECT
                        order_id,
                        MAX(id) AS transaction_id
                    FROM payment_transactions
                    WHERE type = 'charge'
                    AND status = 'succeeded'
                    GROUP BY order_id
                ) AS latest
                    ON latest.transaction_id = pt.id
            ) AS charge
                ON charge.order_id = o.id
            SET
                o.payment_status = 'paid',
                o.payment_method_id =
                    charge.payment_method_id,
                o.payment_method_name =
                    charge.payment_method_name,
                o.payment_method_code =
                    charge.payment_method_code,
                o.payment_provider =
                    charge.provider,
                o.payment_transaction_id =
                    charge.id,
                o.currency = charge.currency,
                o.amount_paid = charge.amount,
                o.amount_refunded =
                    LEAST(
                        o.amount_refunded,
                        charge.amount
                    ),
                o.paid_at =
                    COALESCE(
                        o.paid_at,
                        charge.processed_at,
                        o.placed_at,
                        o.created_at,
                        NOW()
                    ),
                o.payment_failed_at = NULL,
                o.status = CASE
                    WHEN o.status IN (
                        'pending',
                        'processing'
                    )
                    THEN 'paid'
                    ELSE o.status
                END,
                o.updated_at = NOW()
            WHERE
                o.payment_status <> 'paid'
                OR o.payment_transaction_id
                    <> charge.id
                OR o.payment_transaction_id IS NULL
                OR ROUND(o.amount_paid, 2)
                    <> ROUND(charge.amount, 2)
                OR o.payment_method_id
                    <> charge.payment_method_id
                OR o.payment_method_id IS NULL
        ");
    }

    public function down(): void
    {
        /*
         * Data synchronization is intentionally not reversed.
         * The migration only copies verified successful-charge
         * data into its matching order.
         */
    }
};
