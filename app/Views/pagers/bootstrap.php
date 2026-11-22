<?php
/**
 * Bootstrap 5 pagination (registered as 'bootstrap' in Config\Pager).
 *
 * @var \CodeIgniter\Pager\PagerRenderer $pager
 */
$pager->setSurroundCount(2);
?>
<?php if ($pager->getPageCount() > 1) : ?>
<nav aria-label="<?= esc(lang('App.pagination'), 'attr') ?>">
    <ul class="pagination pagination-sm mb-0">
        <?php if ($pager->hasPreviousPage()) : ?>
            <li class="page-item"><a class="page-link" href="<?= $pager->getFirst() ?>" aria-label="<?= esc(lang('App.first'), 'attr') ?>">&laquo;</a></li>
            <li class="page-item"><a class="page-link" href="<?= $pager->getPreviousPage() ?>" aria-label="<?= esc(lang('App.previous'), 'attr') ?>">&lsaquo;</a></li>
        <?php endif ?>

        <?php foreach ($pager->links() as $link) : ?>
            <li class="page-item<?= $link['active'] ? ' active' : '' ?>">
                <a class="page-link" href="<?= $link['uri'] ?>"<?= $link['active'] ? ' aria-current="page"' : '' ?>><?= $link['title'] ?></a>
            </li>
        <?php endforeach ?>

        <?php if ($pager->hasNextPage()) : ?>
            <li class="page-item"><a class="page-link" href="<?= $pager->getNextPage() ?>" aria-label="<?= esc(lang('App.next'), 'attr') ?>">&rsaquo;</a></li>
            <li class="page-item"><a class="page-link" href="<?= $pager->getLast() ?>" aria-label="<?= esc(lang('App.last'), 'attr') ?>">&raquo;</a></li>
        <?php endif ?>
    </ul>
</nav>
<?php endif ?>
