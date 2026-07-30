<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class EmailOutboxRepository
{
    public function __construct(
        private PDO $db
    ) {
    }

    public function all(array $filters = [], int $limit = 50): array
    {
        $where = [];
        $params = [];

        if (! empty($filters['status'])) {
            $where[] = "eo.status = :status";
            $params['status'] = $filters['status'];
        }

        if (! empty($filters['q'])) {
            $where[] = "(
                eo.to_email LIKE :q_email
                OR eo.to_name LIKE :q_name
                OR eo.subject LIKE :q_subject
                OR o.order_number LIKE :q_order_number
            )";

            $search = '%' . $filters['q'] . '%';

            $params['q_email'] = $search;
            $params['q_name'] = $search;
            $params['q_subject'] = $search;
            $params['q_order_number'] = $search;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $this->db->prepare("
            SELECT
                eo.*,
                o.order_number,
                s.name AS store_name
            FROM email_outbox eo
            LEFT JOIN orders o ON o.id = eo.order_id
            LEFT JOIN stores s ON s.id = eo.store_id
            {$whereSql}
            ORDER BY eo.created_at DESC, eo.id DESC
            LIMIT :limit
        ");

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                eo.*,
                o.order_number,
                s.name AS store_name
            FROM email_outbox eo
            LEFT JOIN orders o ON o.id = eo.order_id
            LEFT JOIN stores s ON s.id = eo.store_id
            WHERE eo.id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id,
        ]);

        $email = $stmt->fetch();

        return $email ?: null;
    }

    public function statuses(): array
    {
        return [
            'pending',
            'sent',
            'failed',
        ];
    }

    public function counts(): array
    {
        $stmt = $this->db->query("
            SELECT
                status,
                COUNT(*) AS total
            FROM email_outbox
            GROUP BY status
        ");

        $rows = $stmt->fetchAll();

        $counts = [
            'pending' => 0,
            'sent' => 0,
            'failed' => 0,
        ];

        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }
	
	public function pending(int $limit = 10): array
	{
		$stmt = $this->db->prepare("
			SELECT *
			FROM email_outbox
			WHERE status = 'pending'
			AND attempts < :max_attempts
			ORDER BY created_at ASC, id ASC
			LIMIT :limit
		");

		$stmt->bindValue('max_attempts', (int) ($_ENV['MAIL_MAX_ATTEMPTS'] ?? 3), PDO::PARAM_INT);
		$stmt->bindValue('limit', $limit, PDO::PARAM_INT);

		$stmt->execute();

		return $stmt->fetchAll();
	}

	public function markSent(int $id): void
	{
		$stmt = $this->db->prepare("
			UPDATE email_outbox
			SET
				status = 'sent',
				sent_at = NOW(),
				updated_at = NOW(),
				last_error = NULL
			WHERE id = :id
		");

		$stmt->execute([
			'id' => $id,
		]);
	}

	public function markFailed(int $id, string $error): void
	{
		$stmt = $this->db->prepare("
			UPDATE email_outbox
			SET
				attempts = attempts + 1,
				status = CASE
					WHEN attempts + 1 >= :max_attempts THEN 'failed'
					ELSE 'pending'
				END,
				last_error = :last_error,
				updated_at = NOW()
			WHERE id = :id
		");

		$stmt->execute([
			'id' => $id,
			'max_attempts' => (int) ($_ENV['MAIL_MAX_ATTEMPTS'] ?? 3),
			'last_error' => $error,
		]);
	}
	
	public function pendingById(int $id): ?array
	{
		$stmt = $this->db->prepare("
			SELECT *
			FROM email_outbox
			WHERE id = :id
			AND status = 'pending'
			AND attempts < :max_attempts
			LIMIT 1
		");

		$stmt->bindValue('id', $id, PDO::PARAM_INT);
		$stmt->bindValue(
			'max_attempts',
			(int) ($_ENV['MAIL_MAX_ATTEMPTS'] ?? 3),
			PDO::PARAM_INT
		);

		$stmt->execute();

		$email = $stmt->fetch();

		return $email ?: null;
	}

	public function create(array $data): int
	{
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
				:order_id,
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
			'store_id' => $data['store_id'] ?? null,
			'order_id' => $data['order_id'] ?? null,
			'to_email' => $data['to_email'],
			'to_name' => $data['to_name'] ?? null,
			'subject' => $data['subject'],
			'body_html' => $data['body_html'],
			'body_text' => $data['body_text'] ?? null,
		]);

		return (int) $this->db->lastInsertId();
	}

}