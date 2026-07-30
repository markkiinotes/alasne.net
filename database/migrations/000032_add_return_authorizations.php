<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        $this->addPolicyColumns();
        $this->addReturnColumns();
        $this->backfillExistingAuthorizations();
    }

    public function down(): void
    {
        if ($this->indexExists(
            'returns',
            'uq_returns_rma_number'
        )) {
            $this->db->exec("
                ALTER TABLE returns
                DROP INDEX uq_returns_rma_number
            ");
        }

        foreach (
            [
                'return_shipping_responsibility_snapshot',
                'return_instructions_snapshot',
                'return_address_snapshot',
                'authorization_expires_at',
                'authorization_issued_at',
                'rma_number',
            ]
            as $column
        ) {
            if ($this->columnExists('returns', $column)) {
                $this->db->exec(
                    'ALTER TABLE returns DROP COLUMN '
                    . $column
                );
            }
        }

        foreach (
            [
                'return_address_text',
                'return_address_name',
                'authorization_valid_days',
            ]
            as $column
        ) {
            if (
                $this->columnExists(
                    'return_policies',
                    $column
                )
            ) {
                $this->db->exec(
                    'ALTER TABLE return_policies DROP COLUMN '
                    . $column
                );
            }
        }
    }

    private function addPolicyColumns(): void
    {
        if (! $this->columnExists(
            'return_policies',
            'authorization_valid_days'
        )) {
            $this->db->exec("
                ALTER TABLE return_policies
                ADD COLUMN authorization_valid_days
                    SMALLINT UNSIGNED NOT NULL DEFAULT 30
                AFTER return_window_days
            ");
        }

        if (! $this->columnExists(
            'return_policies',
            'return_address_name'
        )) {
            $this->db->exec("
                ALTER TABLE return_policies
                ADD COLUMN return_address_name
                    VARCHAR(191) NULL
                AFTER return_instructions
            ");
        }

        if (! $this->columnExists(
            'return_policies',
            'return_address_text'
        )) {
            $this->db->exec("
                ALTER TABLE return_policies
                ADD COLUMN return_address_text
                    TEXT NULL
                AFTER return_address_name
            ");
        }
    }

    private function addReturnColumns(): void
    {
        if (! $this->columnExists(
            'returns',
            'rma_number'
        )) {
            $this->db->exec("
                ALTER TABLE returns
                ADD COLUMN rma_number VARCHAR(60) NULL
                AFTER return_number
            ");
        }

        if (! $this->columnExists(
            'returns',
            'authorization_issued_at'
        )) {
            $this->db->exec("
                ALTER TABLE returns
                ADD COLUMN authorization_issued_at
                    DATETIME NULL
                AFTER approved_at
            ");
        }

        if (! $this->columnExists(
            'returns',
            'authorization_expires_at'
        )) {
            $this->db->exec("
                ALTER TABLE returns
                ADD COLUMN authorization_expires_at
                    DATETIME NULL
                AFTER authorization_issued_at
            ");
        }

        if (! $this->columnExists(
            'returns',
            'return_address_snapshot'
        )) {
            $this->db->exec("
                ALTER TABLE returns
                ADD COLUMN return_address_snapshot
                    TEXT NULL
                AFTER authorization_expires_at
            ");
        }

        if (! $this->columnExists(
            'returns',
            'return_instructions_snapshot'
        )) {
            $this->db->exec("
                ALTER TABLE returns
                ADD COLUMN return_instructions_snapshot
                    TEXT NULL
                AFTER return_address_snapshot
            ");
        }

        if (! $this->columnExists(
            'returns',
            'return_shipping_responsibility_snapshot'
        )) {
            $this->db->exec("
                ALTER TABLE returns
                ADD COLUMN
                    return_shipping_responsibility_snapshot
                    VARCHAR(30) NULL
                AFTER return_instructions_snapshot
            ");
        }

        if (! $this->indexExists(
            'returns',
            'uq_returns_rma_number'
        )) {
            $this->db->exec("
                ALTER TABLE returns
                ADD UNIQUE KEY uq_returns_rma_number (
                    rma_number
                )
            ");
        }
    }

    private function backfillExistingAuthorizations(): void
    {
        $this->db->exec("
            UPDATE returns r
            LEFT JOIN return_policies rp
                ON rp.store_id = r.store_id
            SET
                r.rma_number = CONCAT(
                    'RMA-',
                    r.store_id,
                    '-',
                    DATE_FORMAT(
                        COALESCE(
                            r.approved_at,
                            r.created_at
                        ),
                        '%Y%m%d'
                    ),
                    '-',
                    LPAD(r.id, 6, '0')
                ),
                r.authorization_issued_at =
                    COALESCE(
                        r.approved_at,
                        r.created_at
                    ),
                r.authorization_expires_at =
                    TIMESTAMPADD(
                        DAY,
                        COALESCE(
                            rp.authorization_valid_days,
                            30
                        ),
                        COALESCE(
                            r.approved_at,
                            r.created_at
                        )
                    ),
                r.return_address_snapshot =
                    NULLIF(
                        TRIM(
                            CONCAT_WS(
                                '\n',
                                NULLIF(
                                    rp.return_address_name,
                                    ''
                                ),
                                NULLIF(
                                    rp.return_address_text,
                                    ''
                                )
                            )
                        ),
                        ''
                    ),
                r.return_instructions_snapshot =
                    COALESCE(
                        NULLIF(
                            rp.return_instructions,
                            ''
                        ),
                        'Include the return authorization with the merchandise and write the RMA number on the outside of the package.'
                    ),
                r.return_shipping_responsibility_snapshot =
                    CASE
                        WHEN COALESCE(
                            rp.customer_pays_return_shipping,
                            1
                        ) = 1
                        THEN 'customer'
                        ELSE 'store'
                    END
            WHERE r.status IN (
                'approved',
                'received',
                'completed'
            )
            AND r.rma_number IS NULL
        ");
    }

    private function columnExists(
        string $table,
        string $column
    ): bool {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table_name
            AND COLUMN_NAME = :column_name
        ");

        $stmt->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function indexExists(
        string $table,
        string $index
    ): bool {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table_name
            AND INDEX_NAME = :index_name
        ");

        $stmt->execute([
            'table_name' => $table,
            'index_name' => $index,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }
};
