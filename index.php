<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Home';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div class="container">
    <h1>Report it. Track it. Get it fixed.</h1>
    <p>Report maintenance issues on campus, track their progress in real time, and help
       Western Pacific University's maintenance team resolve them faster — all in one place
       for students, staff, academics, and administrators.</p>
  </div>
</section>

<section class="section container">
  <h2>About the System</h2>
  <p class="text-muted" style="max-width:800px;">
    The WPU Maintenance Request System replaces paper-based maintenance requests with a
    single web-based platform. Requesters submit issues with photos and a priority level,
    supervisors assign the work to a technician, technicians log progress and upload
    before/after evidence, and everyone can track a request's status from submission
    through to completion.
  </p>

  <div class="feature-grid" style="margin-top:28px;">
    <div class="feature-card">
      <h3>Submit a Request</h3>
      <p>Students, staff, and academics can report electrical, plumbing, air conditioning,
         furniture, IT equipment, or building repair issues in minutes.</p>
    </div>
    <div class="feature-card">
      <h3>Track Progress</h3>
      <p>Follow a request from Submitted to Assigned, Pending, and Completed, with a full
         history of every status change.</p>
    </div>
    <div class="feature-card">
      <h3>Assign &amp; Resolve</h3>
      <p>Supervisors assign jobs to technicians, who update progress and upload work
         evidence directly from their dashboard.</p>
    </div>
    <div class="feature-card">
      <h3>Reports &amp; Analytics</h3>
      <p>Supervisors and administrators can generate reports by month, building, category,
         technician, and status.</p>
    </div>
  </div>
</section>
<section class="home-actions section">
  <div class="container">
    <div class="hero cta-panel">
      <h2>Get started with the maintenance request system</h2>
      <div class="cta">
        <a class="btn-gold" href="<?= BASE_URL ?>/register.php">Create Account</a>
        <a class="btn-outline" href="<?= BASE_URL ?>/login.php">Sign In</a>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
