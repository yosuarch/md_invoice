<?php
/**
 * User profile dropdown (used by both the client navbar and the admin top bar).
 */
$user = auth()->user();
?>
<?php if ($user !== null) : ?>
    <div class="dropdown">
        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button"
           data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= esc($user->full_name ? up($user->full_name) : ($user->username ?? $user->email), 'attr') ?>">
            <i class="bi bi-person-circle fs-5"></i>
            <span class="d-none d-sm-inline"><?= esc($user->full_name ? up($user->full_name) : ($user->username ?? $user->email)) ?></span>
        </a>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
            <li><h6 class="dropdown-header"><?= esc($user->username) ?> · <?= esc($user->email) ?></h6></li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <a class="dropdown-item" href="<?= url_to('logout') ?>" hx-boost="false">
                    <i class="bi bi-box-arrow-right me-2"></i><?= lang('App.logout') ?>
                </a>
            </li>
        </ul>
    </div>
<?php endif ?>
