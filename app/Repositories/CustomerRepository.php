<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class CustomerRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function all(array $filters = []): array
	{
		$sql = "
			SELECT
				c.id,
				c.store_id,
				c.first_name,
				c.last_name,
				c.email,
				c.phone,
				c.city,
				c.state,
				c.country,
				c.status,
				c.created_at,
				s.name AS store_name
			FROM customers c
			INNER JOIN stores s ON s.id = c.store_id
			WHERE 1 = 1
		";

		$params = [];

		if (! empty($filters['store_id'])) {
			$sql .= " AND c.store_id = :store_id";
			$params['store_id'] = (int) $filters['store_id'];
		}

		if (! empty($filters['status'])) {
			$sql .= " AND c.status = :status";
			$params['status'] = $filters['status'];
		}

		if (! empty($filters['search'])) {
			$sql .= "
				AND (
					c.first_name LIKE :search_first_name
					OR c.last_name LIKE :search_last_name
					OR CONCAT(c.first_name, ' ', c.last_name) LIKE :search_full_name
					OR c.email LIKE :search_email
					OR c.phone LIKE :search_phone
				)
			";

			$search = '%' . $filters['search'] . '%';

			$params['search_first_name'] = $search;
			$params['search_last_name'] = $search;
			$params['search_full_name'] = $search;
			$params['search_email'] = $search;
			$params['search_phone'] = $search;
		}

		$sql .= " ORDER BY c.created_at DESC";

		$stmt = $this->db->prepare($sql);

		$stmt->execute($params);

		return $stmt->fetchAll();
	}
    public function find(int $id): ?array
	{
		$stmt = $this->db->prepare("
			SELECT
				c.id,
				c.store_id,
				c.first_name,
				c.last_name,
				c.email,
				c.phone,
				c.address_line_1,
				c.address_line_2,
				c.city,
				c.state,
				c.postal_code,
				c.country,
				c.status,
				c.created_at,
				s.name AS store_name
			FROM customers c
			INNER JOIN stores s ON s.id = c.store_id
			WHERE c.id = :id
			LIMIT 1
		");

		$stmt->execute([
			'id' => $id,
		]);

		$customer = $stmt->fetch();

		return $customer ?: null;
	}

    public function findByStoreAndEmail(int $storeId, string $email): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, store_id, first_name, last_name, email, phone, status
            FROM customers
            WHERE store_id = :store_id
            AND email = :email
            LIMIT 1
        ");

        $stmt->execute([
            'store_id' => $storeId,
            'email' => $email,
        ]);

        $customer = $stmt->fetch();

        return $customer ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO customers (
                store_id,
                first_name,
                last_name,
                email,
                phone,
                address_line_1,
                address_line_2,
                city,
                state,
                postal_code,
                country,
                status,
                created_at,
                updated_at
            ) VALUES (
                :store_id,
                :first_name,
                :last_name,
                :email,
                :phone,
                :address_line_1,
                :address_line_2,
                :city,
                :state,
                :postal_code,
                :country,
                :status,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'store_id' => $data['store_id'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'] ?: null,
            'phone' => $data['phone'] ?: null,
            'address_line_1' => $data['address_line_1'] ?: null,
            'address_line_2' => $data['address_line_2'] ?: null,
            'city' => $data['city'] ?: null,
            'state' => $data['state'] ?: null,
            'postal_code' => $data['postal_code'] ?: null,
            'country' => $data['country'] ?: 'United States',
            'status' => $data['status'] ?: 'active',
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare("
            UPDATE customers
            SET
                store_id = :store_id,
                first_name = :first_name,
                last_name = :last_name,
                email = :email,
                phone = :phone,
                address_line_1 = :address_line_1,
                address_line_2 = :address_line_2,
                city = :city,
                state = :state,
                postal_code = :postal_code,
                country = :country,
                status = :status,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $id,
            'store_id' => $data['store_id'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'] ?: null,
            'phone' => $data['phone'] ?: null,
            'address_line_1' => $data['address_line_1'] ?: null,
            'address_line_2' => $data['address_line_2'] ?: null,
            'city' => $data['city'] ?: null,
            'state' => $data['state'] ?: null,
            'postal_code' => $data['postal_code'] ?: null,
            'country' => $data['country'] ?: 'United States',
            'status' => $data['status'] ?: 'active',
        ]);
    }
	
	public function stats(int $customerId): array
	{
		return [
			'orders' => $this->orderCount($customerId),
			'revenue' => $this->revenue($customerId),
		];
	}

	protected function orderCount(int $customerId): int
	{
		$stmt = $this->db->prepare("
			SELECT COUNT(*)
			FROM orders
			WHERE customer_id = :customer_id
		");

		$stmt->execute([
			'customer_id' => $customerId,
		]);

		return (int) $stmt->fetchColumn();
	}

	protected function revenue(int $customerId): float
	{
		$stmt = $this->db->prepare("
			SELECT COALESCE(SUM(grand_total), 0)
			FROM orders
			WHERE customer_id = :customer_id
		");

		$stmt->execute([
			'customer_id' => $customerId,
		]);

		return (float) $stmt->fetchColumn();
	}

	public function ordersForCustomer(int $customerId, int $limit = 10): array
	{
		$stmt = $this->db->prepare("
			SELECT
				id,
				order_number,
				status,
				subtotal,
				grand_total,
				placed_at,
				created_at
			FROM orders
			WHERE customer_id = :customer_id
			ORDER BY created_at DESC
			LIMIT :limit
		");

		$stmt->bindValue('customer_id', $customerId, \PDO::PARAM_INT);
		$stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll();
	}
}