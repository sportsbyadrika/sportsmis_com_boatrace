<?php
/**
 * Public landing page — EXTENSION POINT.
 * Intentionally minimal: the spectator-facing experience is still to be
 * specified. See Controllers\PublicController for what is already wired up.
 */
?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-7 text-center">
      <span class="sms-mark d-inline-flex mb-3" style="width:60px;height:60px;font-size:1.8rem">
        <i class="bi bi-water"></i>
      </span>
      <h1 class="fw-bold mb-2">SportsMIS<sup style="font-size:.45em">&reg;</sup> Regatta</h1>
      <div class="sms-subbrand mb-3">Boat Race Event Management</div>
      <p class="text-muted mb-4">
        The public results experience for spectators is coming soon.
        Organisers and race officials can sign in below.
      </p>
      <div class="d-flex flex-wrap gap-2 justify-content-center">
        <a href="/login" class="btn btn-primary"><i class="bi bi-box-arrow-in-right me-1"></i>Sign in</a>
        <a href="/display" class="btn btn-outline-secondary"><i class="bi bi-tv me-1"></i>Display screens</a>
      </div>
    </div>
  </div>
</div>
