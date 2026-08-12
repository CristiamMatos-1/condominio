<?php
/**
 * Partial: _breadcrumbs.php
 * Recebe array $breadcrumbs = [[titulo => url]] ou titulo sem url no final.
 */
$breadcrumbs = $breadcrumbs ?? [
  'Início' => base_url('?route=admin/dashboard')
];
?>
<nav aria-label="breadcrumb" class="breadcrumbs">
  <ol>
    <?php $last = count($breadcrumbs); $i = 0; ?>
    <?php foreach ($breadcrumbs as $label => $url): $i++; ?>
      <li>
        <?php if (is_string($url) && $i < $last): ?>
          <a href="<?= sanitize($url) ?>"><?= sanitize($label) ?></a>
        <?php else: ?>
          <span class="active" aria-current="page"><?= sanitize($label) ?></span>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>
</nav>
