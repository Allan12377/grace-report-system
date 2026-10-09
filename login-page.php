<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= h($school) ?> · Reports</title>
  <link rel="stylesheet" href="assets/style.css">
  <script defer src="assets/app.js"></script>
</head>
<body>
<main class="login-wrap">
  <section class="login-card">
    <div class="login-art">
      <img src="assets/school-logo.jpg" alt="Grace Christian School emblem">
      <div class="eyebrow">ACADEMIC RECORDS</div>
      <h1>Every learner.<br>Every achievement.</h1>
      <p>Manage learner records, enter results, and prepare clear term reports.</p>
      <div class="marquee"><span>GRACE CHRISTIAN PRIMARY SCHOOL, KACUNGWA &nbsp; · &nbsp; THE GRACE OF GOD IS SUFFICIENT &nbsp; · &nbsp;</span></div>
    </div>
    <div class="login-form">
      <div class="brand-mini"><img src="assets/school-logo.jpg" alt="School logo"><span><?= h($school) ?></span></div>
      <p class="eyebrow">ADMINISTRATOR ACCESS</p>
      <h2>Welcome back</h2>
      <p class="muted">Sign in to continue to your school dashboard.</p>
      <?php if(isset($_GET['installed'])): ?><div class="alert success">Installation complete. Sign in with the administrator credentials configured for this system.</div><?php endif; ?>
      <?php if(isset($loginError)): ?><div class="alert error"><?= h($loginError) ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
        <label for="username">Username</label>
        <input id="username" name="username" autocomplete="username" required placeholder="Enter administrator username">
        <label for="password">Password</label>
        <input id="password" type="password" name="password" autocomplete="current-password" required placeholder="Enter password">
        <button class="btn full" type="submit">Sign in securely</button>
      </form>
      <p class="tiny">Authorized administrators only.</p>
    </div>
  </section>
</main>
</body>
</html>
