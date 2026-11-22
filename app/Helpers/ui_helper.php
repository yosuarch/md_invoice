<?php

/**
 * View helpers for forms, badges and permission checks.
 * Controllers redirect back with ->withInput()->with('errors', [...]) on validation failure.
 */

if (! function_exists('form_errors')) {
    /**
     * Validation errors flashed by the last request.
     *
     * @return array<string, string>
     */
    function form_errors(): array
    {
        $errors = session('errors');

        return is_array($errors) ? $errors : [];
    }
}

if (! function_exists('invalid')) {
    /**
     * " is-invalid" when the field has an error (append to the input's class attribute).
     *
     * @param string $field Field name.
     *
     * @return string
     */
    function invalid(string $field): string
    {
        return isset(form_errors()[$field]) ? ' is-invalid' : '';
    }
}

if (! function_exists('field_error')) {
    /**
     * Bootstrap invalid-feedback block for a field (empty when valid).
     *
     * @param string $field Field name.
     *
     * @return string HTML.
     */
    function field_error(string $field): string
    {
        $error = form_errors()[$field] ?? null;

        return $error === null ? '' : '<div class="invalid-feedback">' . esc($error) . '</div>';
    }
}

if (! function_exists('form_value')) {
    /**
     * Value for a form input: old input after a failed submit, else the record value.
     *
     * @param string               $field  Field name.
     * @param array<string, mixed> $record Current record (empty on create).
     * @param mixed                $default Fallback.
     *
     * @return string
     */
    function form_value(string $field, array $record = [], mixed $default = ''): string
    {
        // Unescaped: views escape the result themselves (old() would escape twice).
        $value = old($field, $record[$field] ?? $default, false);

        return $value === null ? '' : (string) $value;
    }
}

if (! function_exists('form_checked')) {
    /**
     * " checked" for a checkbox: old input after a failed submit, else the record value.
     * Render a hidden input with value 0 before each checkbox so unchecked boxes are submitted too.
     *
     * @param string               $field   Field name.
     * @param array<string, mixed> $record  Current record.
     * @param bool                 $default Value on an empty create form.
     *
     * @return string
     */
    function form_checked(string $field, array $record = [], bool $default = false): string
    {
        $old = old($field);

        if ($old !== null) {
            return $old ? ' checked' : '';
        }

        $value = array_key_exists($field, $record) ? (bool) $record[$field] : $default;

        return $value ? ' checked' : '';
    }
}

if (! function_exists('active_badge')) {
    /**
     * Active/Inactive status badge.
     *
     * @param bool|int|string|null $active Truthy = active.
     *
     * @return string HTML.
     */
    function active_badge(bool|int|string|null $active): string
    {
        return $active
            ? '<span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">' . esc(lang('App.active')) . '</span>'
            : '<span class="badge bg-secondary-subtle text-secondary-emphasis border">' . esc(lang('App.inactive')) . '</span>';
    }
}

if (! function_exists('user_can')) {
    /**
     * Whether the logged-in user has a Shield permission.
     *
     * @param string $permission e.g. 'customers.manage'.
     *
     * @return bool
     */
    function user_can(string $permission): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can($permission);
    }
}

if (! function_exists('proposal_status_badge')) {
    /**
     * Badge for a proposal status code (colors per ui_ux_guidelines.md).
     *
     * @param string|null $code client_proposal_status.code
     *
     * @return string HTML.
     */
    function proposal_status_badge(?string $code): string
    {
        $class = match ($code) {
            'DRAFT'         => 'bg-secondary',
            'PROPOSE'       => 'bg-primary',
            'UNDER_REVIEW'  => 'bg-warning text-dark',
            'STORE_APPROVE' => 'bg-success',
            default         => 'bg-danger',
        };

        return '<span class="badge ' . $class . '">' . esc(\App\Services\ProposalWorkflow::label($code)) . '</span>';
    }
}

if (! function_exists('invoice_status_badge')) {
    /**
     * Badge for an invoice status code (colors per ui_ux_guidelines.md).
     *
     * @param string|null $code invoice_status.code
     *
     * @return string HTML.
     */
    function invoice_status_badge(?string $code): string
    {
        $class = match ($code) {
            'DRAFT'          => 'bg-secondary',
            'ISSUED'         => 'bg-primary',
            'PARTIALLY_PAID' => 'bg-info text-dark',
            'PAID'           => 'bg-success',
            'OVERDUE'        => 'bg-danger',
            'CANCELLED'      => 'bg-danger-subtle text-danger-emphasis border border-danger-subtle',
            default          => 'bg-dark',
        };

        return '<span class="badge ' . $class . '">' . esc(\App\Services\InvoiceWorkflow::label($code)) . '</span>';
    }
}

if (! function_exists('format_date')) {
    /**
     * Display a date/datetime as d/m/Y (or d/m/Y H:i).
     *
     * @param string|null $value    Y-m-d or Y-m-d H:i:s.
     * @param bool        $withTime Include time.
     *
     * @return string
     */
    function format_date(?string $value, bool $withTime = false): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return date($withTime ? 'd/m/Y H:i' : 'd/m/Y', strtotime($value));
    }
}

if (! function_exists('pdf_part')) {
    /**
     * Render a PDF partial (app/Views/pdf/_<name>.php) with only the given data. CodeIgniter keeps view data between
     * view() calls by default (saveData), which would let values from one partial leak into another.
     *
     * @param string               $name Partial name without the leading underscore, e.g. 'header'.
     * @param array<string, mixed> $data Data for the partial.
     *
     * @return string HTML.
     */
    function pdf_part(string $name, array $data): string
    {
        return view('pdf/_' . $name, $data, ['saveData' => false]);
    }
}

if (! function_exists('customer_link')) {
    /**
     * Customer name linked to the customer page, or plain text for users without Master Data access.
     *
     * @param int|string  $id    Customer id.
     * @param string|null $name  Customer name.
     * @param string      $class Extra CSS classes for the link.
     *
     * @return string HTML.
     */
    function customer_link(int|string $id, ?string $name, string $class = ''): string
    {
        $text = esc(up($name));

        return user_can('customers.view')
            ? '<a href="' . site_url('customers/' . (int) $id) . '"' . ($class !== '' ? ' class="' . esc($class, 'attr') . '"' : '') . '>' . $text . '</a>'
            : '<span' . ($class !== '' ? ' class="' . esc($class, 'attr') . '"' : '') . '>' . $text . '</span>';
    }
}

if (! function_exists('item_type_label')) {
    /**
     * Label of a product / line type (GOODS, SERVICE) in the screen language.
     *
     * @param string|null $type Stored type code.
     *
     * @return string
     */
    function item_type_label(?string $type): string
    {
        return $type === 'SERVICE' ? lang('App.service') : lang('App.goods');
    }
}

if (! function_exists('code_label')) {
    /**
     * Label of a stored code in the screen language (app/Language/<locale>/Labels.php, key "<group>_<code>").
     * Falls back to $fallback (the English constant) or the code itself.
     *
     * @param string      $group    Label group, e.g. 'proposalStatus', 'schoolStatus', 'reasonType'.
     * @param string|null $code     Stored code.
     * @param string|null $fallback Text when no translation exists.
     * @param string|null $locale   Locale to use instead of the screen language (PDFs: 'id').
     *
     * @return string
     */
    function code_label(string $group, ?string $code, ?string $fallback = null, ?string $locale = null): string
    {
        if ($code === null || $code === '') {
            return (string) $fallback;
        }

        $key  = 'Labels.' . $group . '_' . $code;
        $text = lang($key, [], $locale);

        return $text !== $key ? $text : ($fallback ?? $code);
    }
}

if (! function_exists('localize_lookup')) {
    /**
     * Texts of a lookup definition (Config\Lookups) in the screen language. Keys in app/Language/<locale>/Lookups.php:
     * <slug>_title, <slug>_description, <slug>_col_<column>, <slug>_f_<field>, <slug>_f_<field>_help,
     * <slug>_f_<field>_opt_<value>. Missing keys keep the English text of the config.
     *
     * @param string               $slug Lookup slug (URL part).
     * @param array<string, mixed> $def  Definition from Config\Lookups.
     *
     * @return array<string, mixed>
     */
    function localize_lookup(string $slug, array $def): array
    {
        $t = static function (string $key, string $fallback): string {
            $text = lang('Lookups.' . $key);

            return $text !== 'Lookups.' . $key ? $text : $fallback;
        };
        $p = str_replace('-', '_', $slug);

        foreach (['title', 'description'] as $k) {
            if (isset($def[$k])) {
                $def[$k] = $t("{$p}_{$k}", $def[$k]);
            }
        }

        foreach ($def['columns'] ?? [] as $col => $label) {
            $def['columns'][$col] = $t("{$p}_col_{$col}", $label);
        }

        foreach ($def['fields'] ?? [] as $name => $field) {
            $field['label'] = $t("{$p}_f_{$name}", $field['label']);

            if (isset($field['help'])) {
                $field['help'] = $t("{$p}_f_{$name}_help", $field['help']);
            }

            foreach ($field['options'] ?? [] as $value => $label) {
                $field['options'][$value] = $t("{$p}_f_{$name}_opt_{$value}", $label);
            }

            $def['fields'][$name] = $field;
        }

        return $def;
    }
}
