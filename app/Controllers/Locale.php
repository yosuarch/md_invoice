<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use Throwable;

/**
 * Language toggle (POST locale). Saved on the user's account (users.locale) and in the session, so it also works
 * on the login page. Not audited: a personal preference, not business data.
 */
class Locale extends BaseController
{
    /**
     * Switch the screen language and return to the previous page.
     *
     * @return RedirectResponse
     */
    public function switch(): RedirectResponse
    {
        $locale = (string) $this->request->getPost('locale');

        if (! isset(app_locales()[$locale])) {
            return redirect()->back();
        }

        session()->set('locale', $locale);

        try {
            if (auth()->loggedIn()) {
                db_connect()->table('users')->where('id', auth()->id())->update(['locale' => $locale]);
            }
        } catch (Throwable $e) {
            log_message('error', 'Locale switch: {message}', ['message' => $e->getMessage()]);
        }

        // Back to the page with the toggle: the Referer (same site only), else the last page in the session.
        $back = $this->request->getHeaderLine('Referer') ?: previous_url();

        return redirect()->to(is_string($back) && str_starts_with($back, base_url()) ? $back : site_url('/'));
    }
}
