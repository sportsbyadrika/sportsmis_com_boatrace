<?php
namespace Core;

/**
 * Base controller. Provides view rendering, redirects with flash messages,
 * JSON responses for the AJAX endpoints, CSRF issue/verify and simple
 * server-side validation.
 */
class Controller
{
    protected array $data = [];

    protected function render(string $view, array $data = []): void
    {
        $this->data = array_merge($this->data, $data);
        $this->prepareViewErrors();
        extract($this->data);
        $viewFile = APP_ROOT . "/views/{$view}.php";
        if (!file_exists($viewFile)) {
            http_response_code(500);
            exit("View not found: {$view}");
        }
        require $viewFile;
    }

    /**
     * Render $view inside $layout. The layout `require`s $content, so a view
     * never repeats the page chrome. Layouts live in views/layouts/.
     */
    protected function renderWith(string $layout, string $view, array $data = []): void
    {
        $this->data = array_merge($this->data, $data);
        $this->prepareViewErrors();
        extract($this->data);
        $content    = APP_ROOT . "/views/{$view}.php";
        $layoutFile = APP_ROOT . "/views/layouts/{$layout}.php";
        if (!file_exists($content)) {
            http_response_code(500);
            exit("View not found: {$view}");
        }
        require $layoutFile;
    }

    /** Expose validation errors to fieldError()/hasError() during this render. */
    private function prepareViewErrors(): void
    {
        $errors = $this->data['errors'] ?? $_SESSION['errors'] ?? [];
        $GLOBALS['_sms_errors'] = $errors;
        unset($_SESSION['errors']);
    }

    protected function redirect(string $path, string $message = '', string $type = 'success'): void
    {
        if ($message !== '') {
            $_SESSION['flash'] = ['type' => $type, 'message' => $message];
        }
        header("Location: {$path}");
        exit;
    }

    protected function json(mixed $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function abort(int $code = 403): void
    {
        http_response_code($code);
        $view = APP_ROOT . "/views/errors/{$code}.php";
        require file_exists($view) ? $view : APP_ROOT . '/views/errors/500.php';
        exit;
    }

    protected function old(string $key, mixed $default = ''): mixed
    {
        return $_SESSION['old'][$key] ?? $default;
    }

    protected function flash(): array
    {
        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $flash;
    }

    /**
     * Rule syntax mirrors SportsMIS: 'required|email|min:8|max:100|numeric|date|time'.
     * Errors + old input are stashed in the session for the redirect back.
     */
    protected function validate(array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $rule) {
            $value = $_POST[$field] ?? null;
            $label = ucfirst(str_replace('_', ' ', $field));
            foreach (explode('|', $rule) as $r) {
                [$r, $param] = array_pad(explode(':', $r, 2), 2, null);
                match ($r) {
                    'required' => ($value === null || trim((string)$value) === '') ? $errors[$field][] = "{$label} is required." : null,
                    'email'    => $value && !filter_var($value, FILTER_VALIDATE_EMAIL) ? $errors[$field][] = 'Enter a valid email.' : null,
                    'min'      => $value && strlen((string)$value) < (int)$param ? $errors[$field][] = "Minimum {$param} characters required." : null,
                    'max'      => $value && strlen((string)$value) > (int)$param ? $errors[$field][] = "Maximum {$param} characters allowed." : null,
                    'numeric'  => $value !== null && $value !== '' && !is_numeric($value) ? $errors[$field][] = "{$label} must be numeric." : null,
                    'mobile'   => $value && !preg_match('/^[6-9]\d{9}$/', (string)$value) ? $errors[$field][] = 'Enter a valid 10-digit mobile number.' : null,
                    'date'     => $value && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$value) ? $errors[$field][] = "{$label} must be a valid date." : null,
                    'time'     => $value && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', (string)$value) ? $errors[$field][] = "{$label} must be a valid time." : null,
                    default    => null,
                };
            }
        }
        if ($errors) {
            $_SESSION['old']    = $_POST;
            $_SESSION['errors'] = $errors;
        }
        return $errors;
    }

    protected function errors(): array
    {
        return $_SESSION['errors'] ?? [];
    }

    protected function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Every mutating POST calls this first. A POST larger than php.ini's
     * post_max_size arrives with an EMPTY $_POST, which would otherwise
     * surface as a bare 403 — detect that shape and say "upload too large".
     */
    protected function verifyCsrf(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            && empty($_POST)
            && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $max = (string)ini_get('post_max_size');
            http_response_code(413);
            while (ob_get_level() > 0) { ob_end_clean(); }
            echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
               . '<div style="font-family:Inter,system-ui,sans-serif;max-width:620px;margin:60px auto;padding:28px;border:1px solid #e5e7eb;border-radius:14px">'
               . '<h2 style="color:#b91c1c;margin-top:0">Upload too large</h2>'
               . '<p>The file you tried to upload is larger than the server accepts'
               . ($max !== '' ? ' (server limit &asymp; ' . htmlspecialchars($max, ENT_QUOTES) . ')' : '')
               . ', so your details were <strong>not saved</strong>.</p>'
               . '<p>Please choose a smaller file (max 7&nbsp;MB) and submit again.</p>'
               . '<p style="margin-top:20px"><a href="javascript:history.back()" '
               . 'style="display:inline-block;padding:8px 16px;background:#0b1f3a;color:#fff;border-radius:8px;text-decoration:none">&larr; Go back</a></p>'
               . '</div>';
            exit;
        }
        $token = $_POST['_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            // AJAX callers get JSON rather than an HTML 403 page.
            if (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest') {
                $this->json(['success' => false, 'message' => 'Your session expired. Please reload the page.'], 403);
            }
            $this->abort(403);
        }
    }

    /** True when the current request came from fetch()/XHR. */
    protected function isAjax(): bool
    {
        return strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }
}
