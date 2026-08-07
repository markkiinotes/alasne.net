<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

class MissionControlNotificationTemplateRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function dashboard(array $filters = []): array
    {
        return [
            'summary' => $this->summary(),
            'templates' => $this->templates($filters),
            'events' => $this->recentEvents(25),
            'categories' => $this->categories(),
            'audiences' => $this->audiences(),
        ];
    }

    public function summary(): array
    {
        $stmt = $this->db->query("
            SELECT
                COUNT(*) AS total_templates,
                SUM(is_enabled = 1) AS enabled_templates,
                SUM(is_enabled = 0) AS disabled_templates,
                SUM(audience = 'customer') AS customer_templates,
                SUM(audience = 'supplier') AS supplier_templates,
                SUM(audience = 'admin') AS admin_templates
            FROM mission_control_notification_templates
        ");

        $row = $stmt->fetch() ?: [];

        return [
            'total_templates' => (int) ($row['total_templates'] ?? 0),
            'enabled_templates' => (int) ($row['enabled_templates'] ?? 0),
            'disabled_templates' => (int) ($row['disabled_templates'] ?? 0),
            'customer_templates' => (int) ($row['customer_templates'] ?? 0),
            'supplier_templates' => (int) ($row['supplier_templates'] ?? 0),
            'admin_templates' => (int) ($row['admin_templates'] ?? 0),
        ];
    }

    public function templates(array $filters = []): array
    {
        $sql = "
            SELECT *
            FROM mission_control_notification_templates
            WHERE 1 = 1
        ";
        $params = [];

        if (trim((string) ($filters['category'] ?? '')) !== '') {
            $sql .= ' AND category = :category';
            $params['category'] = trim((string) $filters['category']);
        }

        if (trim((string) ($filters['audience'] ?? '')) !== '') {
            $sql .= ' AND audience = :audience';
            $params['audience'] = trim((string) $filters['audience']);
        }

        if (trim((string) ($filters['search'] ?? '')) !== '') {
            $sql .= " AND (
                template_key LIKE :search
                OR name LIKE :search
                OR description LIKE :search
            )";
            $params['search'] = '%' . trim((string) $filters['search']) . '%';
        }

        $sql .= "
            ORDER BY
                category ASC,
                audience ASC,
                name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM mission_control_notification_templates
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findByKey(string $key): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM mission_control_notification_templates
            WHERE template_key = :template_key
            LIMIT 1
        ");

        $stmt->execute(['template_key' => $key]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function create(array $data, ?int $userId = null): int
    {
        $templateKey = $this->slug((string) ($data['template_key'] ?? ''));

        if ($templateKey === '') {
            throw new RuntimeException('Template key is required.');
        }

        if ($this->findByKey($templateKey)) {
            throw new RuntimeException('Template key already exists.');
        }

        $stmt = $this->db->prepare("
            INSERT INTO mission_control_notification_templates (
                template_key,
                name,
                category,
                audience,
                channel,
                description,
                subject_template,
                body_text_template,
                body_html_template,
                variables_json,
                sample_payload_json,
                is_enabled,
                is_system,
                created_at,
                updated_at
            ) VALUES (
                :template_key,
                :name,
                :category,
                :audience,
                'email',
                :description,
                :subject_template,
                :body_text_template,
                :body_html_template,
                :variables_json,
                :sample_payload_json,
                :is_enabled,
                0,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'template_key' => $templateKey,
            'name' => mb_substr(trim((string) ($data['name'] ?? '')), 0, 191),
            'category' => mb_substr(trim((string) ($data['category'] ?? 'general')), 0, 80),
            'audience' => mb_substr(trim((string) ($data['audience'] ?? 'customer')), 0, 80),
            'description' => $this->nullable($data['description'] ?? null, 1000),
            'subject_template' => mb_substr(trim((string) ($data['subject_template'] ?? '')), 0, 255),
            'body_text_template' => (string) ($data['body_text_template'] ?? ''),
            'body_html_template' => (string) ($data['body_html_template'] ?? ''),
            'variables_json' => $this->jsonOrNull($data['variables_json'] ?? null),
            'sample_payload_json' => $this->jsonOrNull($data['sample_payload_json'] ?? null),
            'is_enabled' => ! empty($data['is_enabled']) ? 1 : 0,
        ]);

        $id = (int) $this->db->lastInsertId();
        $template = $this->find($id);

        if ($template) {
            $this->saveVersion($template, 'Created template.', $userId);
            $this->recordEvent($id, $templateKey, 'created', 'Template created.', $userId);
        }

        return $id;
    }

    public function update(int $id, array $data, ?int $userId = null): void
    {
        $template = $this->find($id);

        if (! $template) {
            throw new RuntimeException('Notification template not found.');
        }

        $stmt = $this->db->prepare("
            UPDATE mission_control_notification_templates
            SET
                name = :name,
                category = :category,
                audience = :audience,
                description = :description,
                subject_template = :subject_template,
                body_text_template = :body_text_template,
                body_html_template = :body_html_template,
                variables_json = :variables_json,
                sample_payload_json = :sample_payload_json,
                is_enabled = :is_enabled,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $id,
            'name' => mb_substr(trim((string) ($data['name'] ?? $template['name'])), 0, 191),
            'category' => mb_substr(trim((string) ($data['category'] ?? $template['category'])), 0, 80),
            'audience' => mb_substr(trim((string) ($data['audience'] ?? $template['audience'])), 0, 80),
            'description' => $this->nullable($data['description'] ?? null, 1000),
            'subject_template' => mb_substr(trim((string) ($data['subject_template'] ?? '')), 0, 255),
            'body_text_template' => (string) ($data['body_text_template'] ?? ''),
            'body_html_template' => (string) ($data['body_html_template'] ?? ''),
            'variables_json' => $this->jsonOrNull($data['variables_json'] ?? null),
            'sample_payload_json' => $this->jsonOrNull($data['sample_payload_json'] ?? null),
            'is_enabled' => ! empty($data['is_enabled']) ? 1 : 0,
        ]);

        $updated = $this->find($id);

        if ($updated) {
            $this->saveVersion(
                $updated,
                trim((string) ($data['change_note'] ?? 'Template updated.')) ?: 'Template updated.',
                $userId
            );

            $this->recordEvent(
                $id,
                (string) $updated['template_key'],
                'updated',
                'Template updated.',
                $userId
            );
        }
    }

    public function recordPreview(int $id, ?int $userId = null): void
    {
        $template = $this->find($id);

        if (! $template) {
            return;
        }

        $stmt = $this->db->prepare("
            UPDATE mission_control_notification_templates
            SET
                preview_count = preview_count + 1,
                last_previewed_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute(['id' => $id]);

        $this->recordEvent(
            $id,
            (string) $template['template_key'],
            'previewed',
            'Template preview generated.',
            $userId
        );
    }

    public function versions(int $templateId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM mission_control_notification_template_versions
            WHERE template_id = :template_id
            ORDER BY version_number DESC, id DESC
            LIMIT 25
        ");

        $stmt->execute(['template_id' => $templateId]);

        return $stmt->fetchAll();
    }

    public function recentEvents(int $limit = 25): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM mission_control_notification_template_events
            ORDER BY created_at DESC, id DESC
            LIMIT " . max(1, min(250, $limit))
        );

        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function categories(): array
    {
        $stmt = $this->db->query("
            SELECT DISTINCT category
            FROM mission_control_notification_templates
            ORDER BY category ASC
        ");

        return array_column($stmt->fetchAll(), 'category');
    }

    public function audiences(): array
    {
        $stmt = $this->db->query("
            SELECT DISTINCT audience
            FROM mission_control_notification_templates
            ORDER BY audience ASC
        ");

        return array_column($stmt->fetchAll(), 'audience');
    }

    public function exportRows(): array
    {
        return $this->templates([]);
    }

    private function saveVersion(
        array $template,
        string $changeNote,
        ?int $userId
    ): void {
        $stmt = $this->db->prepare("
            SELECT COALESCE(MAX(version_number), 0) + 1
            FROM mission_control_notification_template_versions
            WHERE template_id = :template_id
        ");

        $stmt->execute(['template_id' => (int) $template['id']]);
        $version = (int) $stmt->fetchColumn();

        $stmt = $this->db->prepare("
            INSERT INTO mission_control_notification_template_versions (
                template_id,
                version_number,
                change_note,
                subject_template,
                body_text_template,
                body_html_template,
                variables_json,
                sample_payload_json,
                created_by,
                created_at
            ) VALUES (
                :template_id,
                :version_number,
                :change_note,
                :subject_template,
                :body_text_template,
                :body_html_template,
                :variables_json,
                :sample_payload_json,
                :created_by,
                NOW()
            )
        ");

        $stmt->execute([
            'template_id' => (int) $template['id'],
            'version_number' => $version,
            'change_note' => mb_substr($changeNote, 0, 1000),
            'subject_template' => (string) $template['subject_template'],
            'body_text_template' => (string) ($template['body_text_template'] ?? ''),
            'body_html_template' => (string) ($template['body_html_template'] ?? ''),
            'variables_json' => $template['variables_json'] ?? null,
            'sample_payload_json' => $template['sample_payload_json'] ?? null,
            'created_by' => $userId,
        ]);
    }

    private function recordEvent(
        ?int $templateId,
        ?string $templateKey,
        string $eventType,
        string $message,
        ?int $userId
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO mission_control_notification_template_events (
                template_id,
                template_key,
                event_type,
                message,
                created_by,
                created_at
            ) VALUES (
                :template_id,
                :template_key,
                :event_type,
                :message,
                :created_by,
                NOW()
            )
        ");

        $stmt->execute([
            'template_id' => $templateId,
            'template_key' => $templateKey,
            'event_type' => mb_substr($eventType, 0, 60),
            'message' => mb_substr($message, 0, 1000),
            'created_by' => $userId,
        ]);
    }

    private function jsonOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                'Invalid JSON: ' . json_last_error_msg()
            );
        }

        return $value;
    }

    private function slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?: '';
        $value = trim($value, '_');

        return mb_substr($value, 0, 120);
    }

    private function nullable(mixed $value, int $limit): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $limit);
    }
}
