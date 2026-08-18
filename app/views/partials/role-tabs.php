<?php
/** Role picker shown above every sign-in form. $activeRole selects the tab. */
$activeRole = $activeRole ?? 'admin';
$tabs = [
    'admin' => ['/login',             'bi-shield-lock',  'Super Admin'],
    'event' => ['/event-admin/login', 'bi-person-badge', 'Event Admin'],
    'user'  => ['/event-user/login',  'bi-people',       'Event User'],
];
?>
<div class="sms-role-tabs">
  <?php foreach ($tabs as $key => [$href, $icon, $label]): ?>
    <a href="<?= e($href) ?>" class="sms-role-tab <?= $activeRole === $key ? 'active' : '' ?>">
      <i class="bi <?= e($icon) ?>"></i><?= e($label) ?>
    </a>
  <?php endforeach; ?>
</div>
