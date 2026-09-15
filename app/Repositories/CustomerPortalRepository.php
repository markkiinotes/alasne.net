<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

class CustomerPortalRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function storeBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM stores
            WHERE slug = :slug
            LIMIT 1
        ");

        $stmt->execute(['slug' => $slug]);

        $store = $stmt->fetch();

        return $store ?: null;
    }

    public function findCustomerForAccess(
        int $storeId,
        string $email,
        string $postalCode = ''
    ): ?array {
        $email = strtolower(trim($email));
        $postalCode = strtoupper(
            preg_replace('/\s+/', '', trim($postalCode)) ?? ''
        );

        if ($email === '') {
            return null;
        }

        $sql = "
            SELECT *
            FROM customers
            WHERE store_id = :store_id
            AND LOWER(email) = :email
        ";

        $params = [
            'store_id' => $storeId,
            'email' => $email,
        ];

        if ($postalCode !== '') {
            $sql .= "
                AND REPLACE(UPPER(postal_code), ' ', '') = :postal_code
            ";
            $params['postal_code'] = $postalCode;
        }

        $sql .= ' ORDER BY id DESC LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $customer = $stmt->fetch();

        return $customer ?: null;
    }

    public function customer(int $storeId, int $customerId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM customers
            WHERE id = :id
            AND store_id = :store_id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $customerId,
            'store_id' => $storeId,
        ]);

        $customer = $stmt->fetch();

        return $customer ?: null;
    }

    public function createAccessToken(
        int $storeId,
        int $customerId,
        string $email,
        string $token,
        int $ttlMinutes,
        ?string $ipAddress,
        ?string $userAgent
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO customer_portal_access_tokens (
                store_id,
                customer_id,
                email,
                token_hash,
                purpose,
                ip_address,
                user_agent,
                expires_at,
                created_at
            ) VALUES (
                :store_id,
                :customer_id,
                :email,
                :token_hash,
                'login',
                :ip_address,
                :user_agent,
                :expires_at,
                NOW()
            )
        ");

        $stmt->execute([
            'store_id' => $storeId,
            'customer_id' => $customerId,
            'email' => strtolower(trim($email)),
            'token_hash' => hash('sha256', $token),
            'ip_address' => $ipAddress,
            'user_agent' => $this->truncate($userAgent, 500),
            'expires_at' => date('Y-m-d H:i:s', time() + ($ttlMinutes * 60)),
        ]);
    }

    public function tokenByPlainToken(string $token): ?array
    {
        $stmt = $this->db->prepare("
            SELECT cpat.*, s.slug AS store_slug, s.name AS store_name
            FROM customer_portal_access_tokens cpat
            INNER JOIN stores s
                ON s.id = cpat.store_id
            WHERE cpat.token_hash = :token_hash
            AND cpat.used_at IS NULL
            AND cpat.expires_at >= NOW()
            LIMIT 1
        ");

        $stmt->execute([
            'token_hash' => hash('sha256', $token),
        ]);

        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function markTokenUsed(int $tokenId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE customer_portal_access_tokens
            SET used_at = NOW()
            WHERE id = :id
            AND used_at IS NULL
            AND expires_at >= NOW()
        ");

        $stmt->execute([
            'id' => $tokenId,
        ]);

        return $stmt->rowCount() === 1;
    }

    public function recordLogin(
        int $storeId,
        int $customerId,
        ?string $ipAddress,
        ?string $userAgent
    ): void {
        $stmt = $this->db->prepare("
            UPDATE customers
            SET
                portal_last_login_at = NOW(),
                portal_login_count = portal_login_count + 1,
                updated_at = NOW()
            WHERE id = :customer_id
            AND store_id = :store_id
        ");

        $stmt->execute([
            'store_id' => $storeId,
            'customer_id' => $customerId,
        ]);

        $this->logEvent(
            $storeId,
            $customerId,
            'customer_portal_login',
            'Customer portal sign-in',
            'The customer signed in to the account portal.',
            $ipAddress,
            $userAgent
        );
    }

    public function logEvent(
        int $storeId,
        int $customerId,
        string $eventType,
        string $title,
        ?string $description = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO customer_portal_events (
                store_id,
                customer_id,
                event_type,
                title,
                description,
                ip_address,
                user_agent,
                created_at
            ) VALUES (
                :store_id,
                :customer_id,
                :event_type,
                :title,
                :description,
                :ip_address,
                :user_agent,
                NOW()
            )
        ");

        $stmt->execute([
            'store_id' => $storeId,
            'customer_id' => $customerId,
            'event_type' => $this->truncate($eventType, 80),
            'title' => $this->truncate($title, 191),
            'description' => $this->truncate($description, 1000),
            'ip_address' => $this->truncate($ipAddress, 64),
            'user_agent' => $this->truncate($userAgent, 500),
        ]);
    }

    public function queueAccessEmail(
        array $store,
        array $customer,
        string $accessUrl,
        int $ttlMinutes
    ): void {
        $customerName = trim(
            (string) ($customer['first_name'] ?? '')
            . ' '
            . (string) ($customer['last_name'] ?? '')
        );

        $storeName = preg_replace(
            '/[\r\n]+/',
            ' ',
            trim((string) ($store['name'] ?? 'Store'))
        ) ?? 'Store';

        $subject = 'Your secure account link for ' . $storeName;
        $bodyText = "Hi " . ($customerName ?: 'there') . ",\n\n"
            . "Use this secure link to access your customer account for "
            . $store['name']
            . ":\n\n"
            . $accessUrl
            . "\n\nThis link expires in "
            . $ttlMinutes
            . " minutes and can only be used once.\n\n"
            . "If you did not request this link, you can ignore this email.";

        $safeUrl = htmlspecialchars(
            $accessUrl,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
        $safeName = htmlspecialchars(
            $customerName ?: 'there',
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
        $safeStore = htmlspecialchars(
            $storeName,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        $bodyHtml = "
            <div style=\"font-family:Arial,sans-serif;color:#111827;line-height:1.55;\">
                <h1>Your secure account link</h1>
                <p>Hi {$safeName},</p>
                <p>Use the button below to access your customer account for <strong>{$safeStore}</strong>.</p>
                <p><a href=\"{$safeUrl}\" style=\"display:inline-block;background:#111827;color:#ffffff;padding:12px 18px;border-radius:10px;text-decoration:none;font-weight:700;\">Open my account</a></p>
                <p>This link expires in {$ttlMinutes} minutes and can only be used once.</p>
                <p>If you did not request this link, you can ignore this email.</p>
            </div>
        ";

        $stmt = $this->db->prepare("
            INSERT INTO email_outbox (
                store_id,
                order_id,
                to_email,
                to_name,
                subject,
                body_html,
                body_text,
                status,
                attempts,
                created_at,
                updated_at
            ) VALUES (
                :store_id,
                NULL,
                :to_email,
                :to_name,
                :subject,
                :body_html,
                :body_text,
                'pending',
                0,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'store_id' => (int) $store['id'],
            'to_email' => (string) $customer['email'],
            'to_name' => $customerName ?: null,
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
        ]);
    }

    public function summary(int $storeId, int $customerId): array
    {
        $orderStmt = $this->db->prepare("
            SELECT
                COUNT(*) AS order_count,
                COALESCE(
                    SUM(
                        GREATEST(
                            COALESCE(amount_paid, 0)
                            - COALESCE(amount_refunded, 0),
                            0
                        )
                    ),
                    0
                ) AS lifetime_spend,
                MAX(created_at) AS last_order_at
            FROM orders
            WHERE store_id = :store_id
            AND customer_id = :customer_id
            AND COALESCE(payment_status, '') <> 'failed'
        ");
        $orderStmt->execute([
            'store_id' => $storeId,
            'customer_id' => $customerId,
        ]);
        $orders = $orderStmt->fetch() ?: [];

        return [
            'order_count' => (int) ($orders['order_count'] ?? 0),
            'lifetime_spend' => round((float) ($orders['lifetime_spend'] ?? 0), 2),
            'last_order_at' => $orders['last_order_at'] ?? null,
            'return_count' => $this->returnCount($storeId, $customerId),
            'store_credit_balance' => $this->storeCreditBalance($storeId, $customerId),
        ];
    }

    public function orders(int $storeId, int $customerId, int $limit = 25): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM orders
            WHERE store_id = :store_id
            AND customer_id = :customer_id
            AND COALESCE(payment_status, '') <> 'failed'
            ORDER BY created_at DESC, id DESC
            LIMIT " . max(1, min(100, $limit))
        );

        $stmt->execute([
            'store_id' => $storeId,
            'customer_id' => $customerId,
        ]);

        return $stmt->fetchAll();
    }

    public function order(int $storeId, int $customerId, int $orderId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM orders
            WHERE id = :order_id
            AND store_id = :store_id
            AND customer_id = :customer_id
            AND COALESCE(payment_status, '') <> 'failed'
            LIMIT 1
        ");

        $stmt->execute([
            'order_id' => $orderId,
            'store_id' => $storeId,
            'customer_id' => $customerId,
        ]);

        $order = $stmt->fetch();

        return $order ?: null;
    }

    public function orderItems(int $orderId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM order_items
            WHERE order_id = :order_id
            ORDER BY id ASC
        ");

        $stmt->execute(['order_id' => $orderId]);

        return $stmt->fetchAll();
    }

    public function publicOrderEvents(int $orderId): array
    {
        if (! $this->tableExists('order_events')) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM order_events
            WHERE order_id = :order_id
            AND is_public = 1
            ORDER BY created_at ASC, id ASC
        ");

        $stmt->execute(['order_id' => $orderId]);

        return $stmt->fetchAll();
    }

    public function purchaseOrdersForOrder(int $orderId): array
    {
        if (! $this->tableExists('purchase_orders')) {
            return [];
        }

        /*
         * Customer-facing shipment data deliberately excludes
         * supplier identity, supplier references, costs, margin,
         * provider/integration metadata, and submission payloads.
         */
        $stmt = $this->db->prepare("
            SELECT
                po.id,
                po.status,
                po.tracking_status,
                po.shipping_carrier,
                po.tracking_number,
                po.tracking_url,
                po.expected_ship_at,
                po.shipped_at,
                po.delivered_at
            FROM purchase_orders po
            WHERE po.order_id = :order_id
            ORDER BY po.id ASC
        ");

        $stmt->execute(['order_id' => $orderId]);

        return $stmt->fetchAll();
    }

    public function returnsForCustomer(int $storeId, int $customerId): array
    {
        if (! $this->tableExists('returns')) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT r.*, o.order_number
            FROM returns r
            INNER JOIN orders o
                ON o.id = r.order_id
            WHERE o.store_id = :store_id
            AND o.customer_id = :customer_id
            ORDER BY r.created_at DESC, r.id DESC
            LIMIT 25
        ");

        $stmt->execute([
            'store_id' => $storeId,
            'customer_id' => $customerId,
        ]);

        return $stmt->fetchAll();
    }

    public function storeCreditAccount(int $storeId, int $customerId): ?array
    {
        if (! $this->tableExists('store_credit_accounts')) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM store_credit_accounts
            WHERE store_id = :store_id
            AND customer_id = :customer_id
            LIMIT 1
        ");

        $stmt->execute([
            'store_id' => $storeId,
            'customer_id' => $customerId,
        ]);

        $account = $stmt->fetch();

        return $account ?: null;
    }

    public function storeCreditTransactions(int $accountId): array
    {
        if (! $this->tableExists('store_credit_transactions')) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM store_credit_transactions
            WHERE account_id = :account_id
            ORDER BY created_at DESC, id DESC
            LIMIT 25
        ");

        $stmt->execute(['account_id' => $accountId]);

        return $stmt->fetchAll();
    }

    public function updateProfile(
        int $storeId,
        int $customerId,
        array $data,
        ?string $ipAddress,
        ?string $userAgent
    ): array {
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        $line1 = trim((string) ($data['address_line_1'] ?? ''));
        $line2 = trim((string) ($data['address_line_2'] ?? ''));
        $city = trim((string) ($data['city'] ?? ''));
        $state = trim((string) ($data['state'] ?? ''));
        $postal = trim((string) ($data['postal_code'] ?? ''));
        $country = trim((string) ($data['country'] ?? ''));

        if ($firstName === '' || $lastName === '') {
            throw new RuntimeException('First and last name are required.');
        }

        if ($line1 === '' || $city === '' || $state === '' || $postal === '' || $country === '') {
            throw new RuntimeException('Address, city, state, postal code, and country are required.');
        }

        $stmt = $this->db->prepare("
            UPDATE customers
            SET
                first_name = :first_name,
                last_name = :last_name,
                phone = :phone,
                address_line_1 = :address_line_1,
                address_line_2 = :address_line_2,
                city = :city,
                state = :state,
                postal_code = :postal_code,
                country = :country,
                portal_profile_updated_at = NOW(),
                updated_at = NOW()
            WHERE id = :customer_id
            AND store_id = :store_id
        ");

        $stmt->execute([
            'store_id' => $storeId,
            'customer_id' => $customerId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone !== '' ? $phone : null,
            'address_line_1' => $line1,
            'address_line_2' => $line2 !== '' ? $line2 : null,
            'city' => $city,
            'state' => $state,
            'postal_code' => $postal,
            'country' => $country,
        ]);

        $this->logEvent(
            $storeId,
            $customerId,
            'customer_profile_updated',
            'Customer profile updated',
            'The customer updated their account profile from the customer portal.',
            $ipAddress,
            $userAgent
        );

        return $this->customer($storeId, $customerId) ?? [];
    }

    private function returnCount(int $storeId, int $customerId): int
    {
        if (! $this->tableExists('returns')) {
            return 0;
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM returns r
            INNER JOIN orders o
                ON o.id = r.order_id
            WHERE o.store_id = :store_id
            AND o.customer_id = :customer_id
        ");

        $stmt->execute([
            'store_id' => $storeId,
            'customer_id' => $customerId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    private function storeCreditBalance(int $storeId, int $customerId): float
    {
        $account = $this->storeCreditAccount($storeId, $customerId);

        if (! $account) {
            return 0.0;
        }

        return round((float) ($account['balance'] ?? 0), 2);
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table_name
        ");

        $stmt->execute(['table_name' => $table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function truncate(?string $value, int $length): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $length);
    }
}
