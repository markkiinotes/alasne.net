<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\SupplierSubmissionRepository;
use App\Services\Auth\CsrfService;
use App\Services\Suppliers\SupplierSubmissionService;
use RuntimeException;

class SupplierSubmissionController extends Controller
{
    public function __construct(
        private SupplierSubmissionRepository $submissions,
        private SupplierSubmissionService $service,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function index()
    {
        $filters = [
            'q' => trim(
                (string) $this->request->input(
                    'q'
                )
            ),
            'status' => trim(
                (string) $this->request->input(
                    'status'
                )
            ),
            'provider_code' => trim(
                (string) $this->request->input(
                    'provider_code'
                )
            ),
            'supplier_id' => (int)
                $this->request->input(
                    'supplier_id'
                ),
        ];

        return $this->view(
            'admin.supplier-submissions.index',
            [
                'title' =>
                    'Supplier Submission Queue',
                'submissions' =>
                    $this->submissions
                        ->all($filters),
                'suppliers' =>
                    $this->submissions
                        ->suppliers(),
                'filters' => $filters,
                'statuses' => [
                    'prepared',
                    'awaiting_manual',
                    'processing',
                    'submitted',
                    'succeeded',
                    'failed',
                    'cancelled',
                ],
                'success' =>
                    $this->flash(
                        'supplier_submission_success'
                    ),
                'error' =>
                    $this->flash(
                        'supplier_submission_error'
                    ),
            ],
            'admin'
        );
    }

    public function show(Request $request)
    {
        $id = (int) $request->route('id');

        $submission =
            $this->submissions->find($id);

        if (! $submission) {
            http_response_code(404);

            return '404 - Supplier submission not found';
        }

        return $this->view(
            'admin.supplier-submissions.show',
            [
                'title' =>
                    'Supplier Submission #'
                    . $id,
                'submission' => $submission,
                'payload' => json_decode(
                    (string) $submission[
                        'payload_json'
                    ],
                    true
                ) ?: [],
                'events' =>
                    $this->submissions
                        ->events($id),
                'csrf_token' =>
                    $this->csrf->token(),
                'success' =>
                    $this->flash(
                        'supplier_submission_success'
                    ),
                'error' =>
                    $this->flash(
                        'supplier_submission_error'
                    ),
            ],
            'admin'
        );
    }

    public function prepare(Request $request)
    {
        $purchaseOrderId = (int)
            $request->route(
                'purchase_order_id'
            );

        if (! $this->validateCsrf()) {
            return $this->redirectPurchaseOrder(
                $purchaseOrderId,
                'Security token expired.'
            );
        }

        try {
            $submission =
                $this->service
                    ->preparePurchaseOrder(
                        $purchaseOrderId
                    );

            $this->csrf->regenerate();

            $_SESSION[
                'supplier_submission_success'
            ] =
                'Supplier submission prepared successfully.';

            $this->response->redirect(
                '/admin/supplier-submissions/'
                . (int) $submission['id']
            );
        } catch (\Throwable $exception) {
            return $this->redirectPurchaseOrder(
                $purchaseOrderId,
                $exception->getMessage()
                    ?: 'Unable to prepare supplier submission.'
            );
        }
    }

    public function export(Request $request)
    {
        $id = (int) $request->route('id');

        $export =
            $this->service->exportCsv($id);

        header('Content-Type: text/csv');
        header(
            'Content-Disposition: attachment; filename="'
            . str_replace(
                '"',
                '',
                $export['file_name']
            )
            . '"'
        );
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $export['csv'];

        exit;
    }

    public function updateStatus(Request $request)
    {
        $id = (int) $request->route('id');

        if (! $this->validateCsrf()) {
            return $this->redirectSubmission(
                $id,
                'Security token expired.'
            );
        }

        try {
            $status = trim(
                (string) $this->request->input(
                    'status'
                )
            );

            $this->service->markStatus(
                $id,
                $status,
                [
                    'external_order_id' => trim(
                        (string) $this->request
                            ->input(
                                'external_order_id'
                            )
                    ),
                    'error_message' => trim(
                        (string) $this->request
                            ->input(
                                'error_message'
                            )
                    ),
                    'note' => trim(
                        (string) $this->request
                            ->input('note')
                    ),
                    'increment_attempt' =>
                        in_array(
                            $status,
                            [
                                'processing',
                                'submitted',
                                'succeeded',
                                'failed',
                            ],
                            true
                        ),
                ]
            );

            $this->csrf->regenerate();

            $_SESSION[
                'supplier_submission_success'
            ] =
                'Supplier submission updated.';
        } catch (\Throwable $exception) {
            $_SESSION[
                'supplier_submission_error'
            ] =
                $exception->getMessage()
                ?: 'Unable to update supplier submission.';
        }

        $this->response->redirect(
            '/admin/supplier-submissions/'
            . $id
        );
    }

    private function validateCsrf(): bool
    {
        return $this->csrf->validate(
            (string) $this->request->input(
                '_csrf_token'
            )
        );
    }

    private function redirectPurchaseOrder(
        int $purchaseOrderId,
        string $message
    ) {
        $_SESSION[
            'purchase_orders_error'
        ] = $message;

        $this->response->redirect(
            '/admin/purchase-orders/'
            . $purchaseOrderId
        );

        return null;
    }

    private function redirectSubmission(
        int $id,
        string $message
    ) {
        $_SESSION[
            'supplier_submission_error'
        ] = $message;

        $this->response->redirect(
            '/admin/supplier-submissions/'
            . $id
        );

        return null;
    }

    private function flash(
        string $key
    ): ?string {
        $value = $_SESSION[$key] ?? null;

        unset($_SESSION[$key]);

        return $value;
    }
}
