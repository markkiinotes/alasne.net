<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Services\Auth\CsrfService;
use App\Services\Settings\PlatformSettingsService;
use RuntimeException;

class PlatformSettingsController extends Controller
{
    public function __construct(
        private PlatformSettingsService $settings,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function index()
    {
        $success =
            $_SESSION['platform_settings_success']
            ?? null;

        $error =
            $_SESSION['platform_settings_error']
            ?? null;

        $old =
            $_SESSION['platform_settings_old']
            ?? [];

        unset(
            $_SESSION['platform_settings_success'],
            $_SESSION['platform_settings_error'],
            $_SESSION['platform_settings_old']
        );

        return $this->view(
            'admin.settings.index',
            [
                'title' => 'Platform Settings',
                'dashboard' =>
                    $this->settings->dashboard(),
                'csrf_token' =>
                    $this->csrf->token(),
                'success' => $success,
                'error' => $error,
                'old' =>
                    is_array($old)
                        ? $old
                        : [],
            ],
            'admin'
        );
    }

    public function update()
    {
        if (
            ! $this->csrf->validate(
                (string) $this->request->input(
                    '_csrf_token'
                )
            )
        ) {
            $_SESSION['platform_settings_error'] =
                'Security token expired. Please try again.';

            $this->response->redirect(
                '/admin/settings'
            );

            return null;
        }

        $submitted =
            $this->request->input(
                'settings',
                []
            );

        if (! is_array($submitted)) {
            $_SESSION['platform_settings_error'] =
                'Invalid settings payload.';

            $this->response->redirect(
                '/admin/settings'
            );

            return null;
        }

        $_SESSION['platform_settings_old'] =
            $submitted;

        try {
            $changed =
                $this->settings->update(
                    $submitted,
                    current_user_id()
                );

            $this->csrf->regenerate();

            unset(
                $_SESSION[
                    'platform_settings_old'
                ]
            );

            $_SESSION[
                'platform_settings_success'
            ] =
                $changed > 0
                    ? $changed
                        . ' platform setting'
                        . ($changed === 1 ? '' : 's')
                        . ' updated successfully.'
                    : 'No platform setting changes were required.';

            $this->response->redirect(
                '/admin/settings'
            );

            return null;
        } catch (\Throwable $exception) {
            $_SESSION['platform_settings_error'] =
                $exception->getMessage()
                ?: 'Unable to update platform settings.';

            $this->response->redirect(
                '/admin/settings'
            );

            return null;
        }
    }
}
