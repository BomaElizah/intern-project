<?php
include 'auth.php';
requireLogin();
$user = getCurrentUser();
requireRole(['Supervisor','Administrator']);

// Fetch filter lists
$buildings = $conn->query("SELECT building_id, building_name FROM buildings ORDER BY building_name");
$categories = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name");
$technicians = $conn->query("SELECT user_id, full_name FROM users WHERE role_id IN (SELECT role_id FROM roles WHERE LOWER(role_name) IN ('maintenance officer','technician')) ORDER BY full_name");

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Reports - WPU MRS</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <header class="header">
    <div class="container header-top">
      <div class="brand">
        <h1>Reports</h1>
        <p>Generate reports with filters, view charts, and export CSV.</p>
      </div>
      <nav>
        <a href="dashboard_supervisor.php">Back</a>
        <a href="logout.php">Logout</a>
      </nav>
    </div>
  </header>

  <main class="main-container">
    <section class="page-banner">
      <h2>Reporting Tools</h2>
      <p class="secondary-text">Choose filters and click Generate to view results and charts.</p>
    </section>

    <div class="dashboard-container">
      <section class="report-filters">
        <form id="reportForm" method="POST" action="generate_report.php">
          <label for="report_type">Report Type</label>
          <select id="report_type" name="report_type">
            <option value="category">By Category</option>
            <option value="building">By Building</option>
            <option value="technician">By Technician</option>
            <option value="priority">By Priority</option>
            <option value="monthly">By Month</option>
            <option value="status">By Status</option>
          </select>

          <label for="start_date">Start Date</label>
          <input type="date" id="start_date" name="start_date">

          <label for="end_date">End Date</label>
          <input type="date" id="end_date" name="end_date">

          <label for="building">Building</label>
          <select id="building" name="building">
            <option value="">Any</option>
            <?php while ($b = $buildings->fetch_assoc()): ?>
              <option value="<?php echo e($b['building_id']); ?>"><?php echo e($b['building_name']); ?></option>
            <?php endwhile; ?>
          </select>

          <label for="category">Category</label>
          <select id="category" name="category">
            <option value="">Any</option>
            <?php while ($c = $categories->fetch_assoc()): ?>
              <option value="<?php echo e($c['category_id']); ?>"><?php echo e($c['category_name']); ?></option>
            <?php endwhile; ?>
          </select>

          <label for="technician">Technician</label>
          <select id="technician" name="technician">
            <option value="">Any</option>
            <?php while ($t = $technicians->fetch_assoc()): ?>
              <option value="<?php echo e($t['user_id']); ?>"><?php echo e($t['full_name']); ?></option>
            <?php endwhile; ?>
          </select>

          <label for="status">Status</label>
          <select id="status" name="status">
            <option value="">Any</option>
            <option value="Submitted">Submitted</option>
            <option value="Assigned">Assigned</option>
            <option value="Pending">Pending</option>
            <option value="Completed">Completed</option>
            <option value="Rejected">Rejected</option>
          </select>

          <div class="form-actions">
            <button id="generateBtn" type="submit" class="btn-primary">Generate</button>
            <button id="exportBtn" type="button" class="btn-secondary">Export CSV</button>
          </div>
        </form>
      </section>

      <section class="report-results">
        <h3>Chart</h3>
        <div id="chartWrap"><canvas id="reportChart"></canvas></div>
        <h3>Results</h3>
        <div id="resultsTable"></div>
      </section>
    </div>
  </main>

  <script src="csrf.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
  <script>
    const form = document.getElementById('reportForm');
    const chartCtx = document.getElementById('reportChart').getContext('2d');
    let chart = null;

    form.addEventListener('submit', function(e){
      e.preventDefault();
      const data = new FormData(form);
      data.append('ajax','1');
      fetch(form.action, { method: 'POST', body: data, credentials: 'same-origin' })
        .then(r=>r.json())
        .then(resp=>{
          const rows = resp.rows || [];
          renderTable(rows);
          renderChart(rows);
        }).catch(err=>{ console.error(err); alert('Report generation failed.'); });
    });

    document.getElementById('exportBtn').addEventListener('click', function(){
      const data = new FormData(form);
      data.append('export','csv');
      // Submit via form to trigger CSV download
      form.submit();
    });

    function renderTable(rows){
      const container = document.getElementById('resultsTable');
      if (!rows.length){ container.innerHTML = '<p>No results</p>'; return; }
      let html = '<table><thead><tr>' + Object.keys(rows[0]).map(h=>'<th>'+h+'</th>').join('') + '</tr></thead><tbody>';
      rows.forEach(r=>{ html += '<tr>' + Object.values(r).map(v=>'<td>'+v+'</td>').join('') + '</tr>'; });
      html += '</tbody></table>';
      container.innerHTML = html;
    }

    function renderChart(rows){
      if (chart) chart.destroy();
      const labels = rows.map(r=>r.label || r.period || Object.values(r)[0]);
      const data = rows.map(r=>parseInt(r.total_requests || Object.values(r)[1] || 0));
      chart = new Chart(chartCtx, { type: 'bar', data: { labels: labels, datasets: [{ label: 'Count', data: data, backgroundColor: '#36A2EB' }] }, options: { responsive: true } });
    }
  </script>
</body>
</html>
