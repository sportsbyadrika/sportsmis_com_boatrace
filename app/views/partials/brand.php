<?php
/**
 * The SportsMIS® · Regatta lockup. $brandDark = true on the navy navbar.
 * Kept in one partial so every chrome (admin, portal, auth, display) shows
 * exactly the same mark.
 */
$brandDark = $brandDark ?? true;
$brandHref = $brandHref ?? '/';
?>
<a class="navbar-brand sms-lockup" href="<?= e($brandHref) ?>">
  <span class="sms-mark"><i class="bi bi-water"></i></span>
  <span class="sms-wordmark">
    SportsMIS<sup>&reg;</sup>
    <span class="sms-subbrand">Regatta</span>
  </span>
</a>
