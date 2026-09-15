<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        /*
         * Repair only authorizations whose address snapshot
         * is NULL or blank. Existing nonblank snapshots remain
         * immutable and are never overwritten.
         */
        $this->db->exec("
            UPDATE returns r
            INNER JOIN return_policies rp
                ON rp.store_id = r.store_id
            SET
                r.return_address_snapshot =
                    NULLIF(
                        TRIM(
                            CONCAT_WS(
                                '\n',
                                NULLIF(
                                    TRIM(
                                        rp.return_address_name
                                    ),
                                    ''
                                ),
                                NULLIF(
                                    TRIM(
                                        rp.return_address_text
                                    ),
                                    ''
                                )
                            )
                        ),
                        ''
                    ),
                r.updated_at = NOW()
            WHERE r.rma_number IS NOT NULL
            AND (
                r.return_address_snapshot IS NULL
                OR TRIM(
                    r.return_address_snapshot
                ) = ''
            )
            AND (
                NULLIF(
                    TRIM(
                        rp.return_address_name
                    ),
                    ''
                ) IS NOT NULL
                OR NULLIF(
                    TRIM(
                        rp.return_address_text
                    ),
                    ''
                ) IS NOT NULL
            )
        ");
    }

    public function down(): void
    {
        /*
         * Data repair is intentionally not reversed.
         * Removing a valid authorization address would make
         * repaired RMAs unusable again.
         */
    }
};
