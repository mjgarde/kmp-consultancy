<?php

session_name('STAFF_SESSION');
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'staff') {
    header('Location: ../login.php');
    exit;
}

$services = [
    [
        'category' => 'Business Registration',
        'name' => 'DTI Business Name Registration',
        'summary' => 'Registration of a business name for a sole proprietor.',
        'includes' => ['DTI business name', 'Barangay clearance', "Mayor's permit", 'Guide to requirements'],
        'rates' => [['label' => 'Service fee', 'min' => 1500, 'max' => 5000, 'unit' => 'per registration', 'fmt' => 'peso', 'est' => true]],
        'note' => 'Government fees (DTI, barangay, LGU) are separate.',
    ],
    [
        'category' => 'Business Registration',
        'name' => 'SEC Registration (Corporation or Partnership)',
        'summary' => 'Registration of a corporation or partnership with the SEC.',
        'includes' => ['Name reservation', 'Articles of Incorporation', 'By-Laws', 'SEC filing'],
        'rates' => [['label' => 'Service fee', 'min' => 10000, 'max' => 40000, 'unit' => 'per registration', 'fmt' => 'peso']],
        'note' => 'SEC fees are separate.',
    ],
    [
        'category' => 'Business Registration',
        'name' => 'Business Permit Renewal',
        'summary' => 'Annual renewal of the business permit.',
        'includes' => ["Mayor's permit", 'Sanitary permit', 'Fire safety certificate', 'Barangay clearance'],
        'rates' => [['label' => 'Service fee', 'min' => 1000, 'max' => 5000, 'unit' => 'per year', 'fmt' => 'peso', 'est' => true]],
        'note' => 'LGU and BFP fees are separate.',
    ],
    [
        'category' => 'BIR and Taxes',
        'name' => 'BIR Registration',
        'summary' => 'Registration of the business with the BIR.',
        'includes' => ['TIN', 'Certificate of Registration (Form 2303)', 'Books of accounts', 'Authority to print receipts and invoices'],
        'rates' => [['label' => 'Service fee', 'min' => 1500, 'max' => 5000, 'unit' => 'per registration', 'fmt' => 'peso', 'est' => true]],
        'note' => 'BIR fees are separate.',
    ],
    [
        'category' => 'BIR and Taxes',
        'name' => 'BIR Tax Filing',
        'summary' => 'Preparation and filing of monthly, quarterly, and annual tax returns.',
        'includes' => ['Percentage tax and VAT', 'Quarterly income tax', 'Annual income tax', 'Withholding tax'],
        'rates' => [['label' => 'Filing fee', 'min' => 1000, 'max' => 8000, 'unit' => 'per filing, depends on the type of return', 'fmt' => 'peso', 'est' => true]],
        'note' => 'Taxes are paid by the client.',
    ],
    [
        'category' => 'Accounting',
        'name' => 'Bookkeeping',
        'summary' => 'Recording the income and expenses of the business.',
        'includes' => ['Recording of transactions', 'Monthly financial report', 'Invoice and ledger templates'],
        'rates' => [['label' => 'Monthly fee', 'min' => 2000, 'max' => 15000, 'unit' => 'per month, depends on the volume of transactions', 'fmt' => 'peso']],
        'note' => 'External audit is not included.',
    ],
    [
        'category' => 'Accounting',
        'name' => 'Financial Statements',
        'summary' => 'Annual report of the income, expenses, and assets of the business.',
        'includes' => ['Income statement', 'Balance sheet', 'Cash flow', 'For bank, SEC, or BIR'],
        'rates' => [['label' => 'Service fee', 'min' => 5000, 'max' => 30000, 'unit' => 'per year', 'fmt' => 'peso']],
        'note' => 'External audit is not included.',
    ],
    [
        'category' => 'Legal Documents',
        'name' => 'Paralegal (Legal Papers)',
        'summary' => 'Assistance in drafting and reviewing legal documents.',
        'includes' => ['Contract drafting', 'Contract review', 'Labor law assistance', 'Document filing'],
        'rates' => [['label' => 'Service fee', 'min' => 3000, 'max' => 25000, 'unit' => 'per job', 'fmt' => 'peso']],
        'note' => 'This is not a substitute for a lawyer.',
    ],
    [
        'category' => 'Advisory and Training',
        'name' => 'Business Consultation',
        'summary' => 'Advice on how to grow and improve the business.',
        'includes' => ['Business planning', 'Startup advice', 'Process improvement', 'Project management'],
        'rates' => [['label' => 'Service fee', 'min' => 1000, 'max' => 20000, 'unit' => 'per session or project', 'fmt' => 'peso', 'est' => true]],
        'note' => '',
    ],
    [
        'category' => 'Advisory and Training',
        'name' => 'Training and Workshops',
        'summary' => 'Training for employees and organizations.',
        'includes' => ['Leadership and soft skills', 'HRIS and compliance', 'Onsite, Zoom, or hybrid'],
        'rates' => [['label' => 'Training fee', 'min' => 3000, 'max' => 30000, 'unit' => 'per session, depends on the number of participants', 'fmt' => 'peso']],
        'note' => '',
    ],
    [
        'category' => 'Marketing',
        'name' => 'Marketing and Social Media',
        'summary' => 'Promoting and growing the business online and offline.',
        'includes' => ['Branding', 'Social media management', 'Customer retention', 'Competitor study'],
        'rates' => [['label' => 'Fee per campaign', 'min' => 5000, 'max' => 50000, 'unit' => 'per campaign', 'fmt' => 'peso']],
        'note' => '',
    ],
];

$categoryIcons = [
    'Business Registration' => 'fa-building',
    'BIR and Taxes' => 'fa-file-invoice',
    'Accounting' => 'fa-calculator',
    'Legal Documents' => 'fa-scale-balanced',
    'Advisory and Training' => 'fa-chalkboard-user',
    'Marketing' => 'fa-bullhorn',
];

function peso(int $amount): string
{
    return '₱' . number_format($amount);
}

function fmtVal(int $value, string $fmt): string
{
    return $fmt === 'percent' ? $value . '%' : peso($value);
}

function rateText(array $rate): string
{
    if ($rate['max'] === null) {
        return 'Starting at ' . fmtVal($rate['min'], $rate['fmt']);
    }
    return fmtVal($rate['min'], $rate['fmt']) . ' – ' . fmtVal($rate['max'], $rate['fmt']);
}

$categories = [];
$jsData = [];
foreach ($services as $i => &$s) {
    $s['id'] = $i + 1;
    $categories[$s['category']] = ($categories[$s['category']] ?? 0) + 1;

    $rateList = [];
    foreach ($s['rates'] as $r) {
        $rateList[] = ['label' => $r['label'], 'text' => rateText($r), 'unit' => $r['unit'], 'est' => !empty($r['est'])];
    }
    $jsData[$s['id']] = [
        'name' => $s['name'],
        'category' => $s['category'],
        'summary' => $s['summary'],
        'includes' => $s['includes'],
        'note' => $s['note'],
        'rates' => $rateList,
    ];
}
unset($s);

$totalServices = count($services);
$estCount = count(array_filter($services, fn($s) => !empty($s['rates'][0]['est'])));

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Services and Pricing</title>
<link rel="stylesheet" href="../assets/vendor/bootstrap-5.3.8/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/vendor/fontawesome-free-7.3.1/css/all.min.css">
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lexend:wght@500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --navy: #0F172A;
  --navy-2: #1B2540;
  --indigo: #3B4E8A;
  --indigo-text: #2E3E70;
  --indigo-soft: #E4E9F7;
  --slate: #475569;
  --ink: #1A2233;
  --muted: #667085;
  --line: #E2E5EB;
  --canvas: #F3F5F9;
  --green: #157A5F;
  --amber-soft: #FBF0DD;
  --amber-text: #8A5A15;
  --amber-line: #EFD8A8;
  --red-soft: #FAECE8;
  --red-text: #93382A;
}

body {
  background: var(--canvas);
  color: var(--ink);
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  -webkit-font-smoothing: antialiased;
  overflow-x: hidden;
}
.dashboard-layout, .dashboard-main, .dashboard-content { background: var(--canvas) !important; }
.dashboard-main { min-width: 0; max-width: 100%; }
.dashboard-title, h1, h2, h3, h4 { font-family: 'Lexend', 'Inter', sans-serif; }
.dashboard-title { color: var(--navy); letter-spacing: -0.01em; }
.dashboard-subtitle { color: var(--muted) !important; }
.dashboard-topbar { border-bottom: 1px solid var(--line) !important; background: #fff; }

.sv-banner {
  background: #FFFFFF;
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 1.5rem 1.75rem;
  margin-bottom: 1rem;
  display: grid;
  grid-template-columns: 1.3fr repeat(4, 1fr);
  align-items: center;
  gap: 1.25rem;
}
.sv-banner-intro h2 { font-size: 1.15rem; font-weight: 700; color: #000000; margin: 0 0 .3rem; letter-spacing: -0.01em; }
.sv-banner-intro p { font-size: .8rem; color: #000000; margin: 0; line-height: 1.5; }
.sv-stat { text-align: center; padding: .25rem 0; background: var(--canvas); border-radius: 10px; padding: .95rem .5rem; }
.sv-stat-n { display: block; font-family: 'Lexend', sans-serif; font-size: 1.75rem; font-weight: 700; color: var(--navy); line-height: 1; }
.sv-stat-l { display: block; margin-top: .45rem; font-size: .66rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); }
.sv-stat.is-green .sv-stat-n { color: #0F5F49; }
.sv-stat.is-amber .sv-stat-n { color: #8A5A15; }

.sv-toolbar { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 1rem 1.15rem; margin-bottom: 1rem; }
.sv-bar { display: flex; flex-wrap: wrap; gap: .6rem; align-items: center; }
.sv-search { position: relative; flex: 1 1 320px; max-width: 520px; }
.sv-search i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: .85rem; }
.sv-search input, .sv-select {
  height: 40px;
  border: 1px solid #D5DAE3;
  border-radius: 8px;
  background: #fff;
  font-size: .85rem;
  color: var(--ink);
  outline: none;
}
.sv-search input { width: 100%; padding: 0 .9rem 0 2.2rem; }
.sv-select { padding: 0 2rem 0 .8rem; min-width: 190px; }
.sv-search input:focus, .sv-select:focus { border-color: var(--indigo); box-shadow: 0 0 0 .2rem #DDE2F1; }
.sv-shown { margin-left: auto; font-size: .78rem; color: var(--muted); }

.sv-chips { display: flex; gap: .45rem; flex-wrap: wrap; margin-top: .9rem; padding-top: .9rem; border-top: 1px solid var(--line); }
.sv-chip {
  display: inline-flex;
  align-items: center;
  gap: .45rem;
  border: 1px solid #D5DAE3;
  background: #fff;
  color: var(--slate);
  border-radius: 999px;
  padding: .4rem .95rem;
  font-size: .78rem;
  font-weight: 600;
  cursor: pointer;
}
.sv-chip b { font-weight: 600; color: var(--muted); }
.sv-chip.is-active { background: var(--navy); border-color: var(--navy); color: #fff; }
.sv-chip.is-active b { color: #C9D1E6; }

.sv-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem; }
.sv-card {
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 1.2rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
  cursor: pointer;
}
.sv-card-top { display: flex; align-items: center; justify-content: space-between; gap: .75rem; }
.sv-icon { width: 42px; height: 42px; border-radius: 10px; background: var(--indigo); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; }
.sv-tag { display: inline-block; background: var(--amber-soft); color: var(--amber-text); border: 1px solid var(--amber-line); font-size: .62rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; border-radius: 6px; padding: .2rem .5rem; }
.sv-tag.is-set { background: #E3F3EC; color: #0F5F49; border-color: #BFE3D3; }
.sv-card-body { flex: 1 1 auto; }
.sv-cat { display: inline-block; font-size: .68rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--muted); margin-bottom: .35rem; }
.sv-name { font-size: 1rem; font-weight: 600; color: var(--navy); line-height: 1.35; margin: 0 0 .35rem; font-family: 'Lexend', 'Inter', sans-serif; }
.sv-sum { font-size: .8rem; color: var(--muted); line-height: 1.5; margin: 0; }
.sv-price { background: var(--canvas); border-radius: 10px; padding: .8rem .95rem; }
.sv-price-l { font-size: .64rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); }
.sv-price-v { font-family: 'Lexend', sans-serif; font-size: 1.2rem; font-weight: 700; color: var(--indigo-text); margin-top: .2rem; font-variant-numeric: tabular-nums; }
.sv-price-u { font-size: .75rem; color: var(--slate); margin-top: .15rem; }
.sv-price-m { font-size: .7rem; color: var(--muted); margin-top: .25rem; }
.sv-card-foot { display: flex; align-items: center; justify-content: space-between; font-size: .78rem; font-weight: 600; color: var(--indigo-text); }
.sv-empty { display: none; text-align: center; color: var(--muted); padding: 3rem 1rem; background: #fff; border: 1px solid var(--line); border-radius: 12px; }

.sv-modal .modal-content { border: none; border-radius: 12px; background: #fff; overflow: hidden; }
.sv-modal .modal-header { border-bottom: none; background: var(--navy); padding: 1.2rem 1.5rem; align-items: flex-start; gap: 1rem; }
.sv-modal-cat { display: inline-block; background: var(--indigo-soft); color: var(--indigo-text); border-radius: 6px; padding: .22rem .6rem; font-size: .72rem; font-weight: 600; }
.sv-modal-title { font-size: 1.2rem; font-weight: 700; color: #FFFFFF; margin: .55rem 0 0; letter-spacing: -0.01em; }
.sv-modal .btn-close { margin: 0 0 0 auto; width: 34px; height: 34px; padding: 0; background-size: 14px; opacity: 1; border-radius: 8px; flex-shrink: 0; }
.sv-modal .btn-close:focus { box-shadow: none; }
.sv-modal .modal-body { padding: 1.4rem 1.5rem 1.6rem; background: var(--canvas); }
.sv-modal-sum { font-size: .88rem; color: var(--slate); margin: 0 0 1.25rem; line-height: 1.55; }
.sv-sec { font-family: 'Inter', sans-serif; font-size: .68rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); margin: 1.4rem 0 .6rem; }
.sv-sec.first { margin-top: 0; }
.sv-rate-card { background: #fff; border: 1px solid var(--line); border-radius: 10px; padding: .85rem 1.1rem; margin-bottom: .6rem; }
.sv-rate-card .k { font-size: .74rem; font-weight: 600; color: var(--muted); display: flex; align-items: center; gap: .5rem; }
.sv-rate-card .v { font-family: 'Lexend', sans-serif; font-size: 1.45rem; font-weight: 700; color: var(--indigo-text); margin-top: .2rem; }
.sv-rate-card .u { font-size: .78rem; color: var(--slate); margin-top: .15rem; }
.sv-inc { list-style: none; padding: 0; margin: 0; display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; }
.sv-inc li { display: flex; gap: .6rem; font-size: .84rem; line-height: 1.4; background: #fff; border: 1px solid var(--line); border-radius: 8px; padding: .6rem .8rem; }
.sv-inc i { color: var(--green); font-size: .75rem; margin-top: .28rem; }
.sv-note { display: flex; gap: .65rem; background: var(--red-soft); color: var(--red-text); border-radius: 8px; padding: .7rem .9rem; font-size: .82rem; margin-top: 1.1rem; }

@media (max-width: 1199.98px) {
  .sv-banner { grid-template-columns: repeat(4, 1fr); }
  .sv-banner-intro { grid-column: 1 / -1; }
}

@media (max-width: 767.98px) {
  .dashboard-content { padding: .75rem !important; }
  .dashboard-title { font-size: .92rem !important; }
  .sv-banner { grid-template-columns: repeat(2, 1fr); padding: 1.1rem; gap: .7rem; }
  .sv-stat-n { font-size: 1.4rem; }
  .sv-toolbar { padding: .85rem; }
  .sv-search { max-width: none; flex: 1 1 100%; }
  .sv-select { flex: 1 1 100%; width: 100%; }
  .sv-shown { margin-left: 0; width: 100%; }
  .sv-chips { flex-wrap: nowrap; overflow-x: auto; scrollbar-width: none; }
  .sv-chips::-webkit-scrollbar { display: none; }
  .sv-chip { flex: 0 0 auto; white-space: nowrap; }
  .sv-grid { grid-template-columns: 1fr; }
  .sv-modal .modal-header { padding: 1rem 1.1rem; }
  .sv-modal .modal-body { padding: 1.1rem; }
  .sv-modal-title { font-size: 1.05rem; }
  .sv-inc { grid-template-columns: 1fr; }
}

@media (prefers-reduced-motion: reduce) {
  * { transition: none !important; }
}
</style>
</head>
<body>

<div class="dashboard-layout d-flex">

<?php require __DIR__ . '/../includes/staff/sidebar.php'; ?>

  <div class="dashboard-main flex-grow-1" style="min-width:0;">

    <header class="dashboard-topbar d-flex align-items-center justify-content-between px-3 px-md-4 py-2">
      <div class="d-flex align-items-center gap-3">
        <button type="button" class="btn btn-link text-dark p-0 d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Open menu">
          <i class="fa-solid fa-bars fs-5"></i>
        </button>
        <div>
          <h1 class="dashboard-title h6 h5-md fw-bold mb-0">Services and Pricing</h1>
          <p class="dashboard-subtitle small mb-0 d-none d-sm-block">Quick pricing guide when a client asks.</p>
        </div>
      </div>
    </header>

    <main class="dashboard-content p-3 p-md-4">

      <section class="sv-banner">
        <div class="sv-banner-intro">
          <h2>Service Catalog</h2>
          <p>Standard services and rate ranges offered to clients.</p>
        </div>
        <div class="sv-stat"><span class="sv-stat-n"><?= $totalServices ?></span><span class="sv-stat-l">Services</span></div>
        <div class="sv-stat"><span class="sv-stat-n"><?= count($categories) ?></span><span class="sv-stat-l">Categories</span></div>
        <div class="sv-stat is-green"><span class="sv-stat-n"><?= $totalServices - $estCount ?></span><span class="sv-stat-l">Confirmed</span></div>
        <div class="sv-stat is-amber"><span class="sv-stat-n"><?= $estCount ?></span><span class="sv-stat-l">Estimated</span></div>
      </section>

      <section class="sv-toolbar">
        <div class="sv-bar">
          <div class="sv-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="svSearch" placeholder="Search for a service." autocomplete="off">
          </div>
          <select id="svSort" class="sv-select">
            <option value="default">Sort: Default</option>
            <option value="name">Name A-Z</option>
            <option value="low">Cheapest first</option>
            <option value="high">Most expensive first</option>
          </select>
          <div class="sv-shown"><span id="svCount"><?= $totalServices ?></span> of <?= $totalServices ?> services</div>
        </div>
        <div class="sv-chips" id="svChips">
          <button type="button" class="sv-chip is-active" data-cat="all">All<b><?= $totalServices ?></b></button>
          <?php foreach ($categories as $cat => $n): ?>
            <button type="button" class="sv-chip" data-cat="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?><b><?= $n ?></b></button>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="sv-grid" id="svGrid">
        <?php foreach ($services as $s): ?>
          <?php
            $r = $s['rates'][0];
            $isPeso = $r['fmt'] === 'peso';
            $sortMin = $isPeso ? $r['min'] : -1;
            $sortMax = $isPeso ? ($r['max'] ?? $r['min']) : -1;
            $blob = strtolower($s['name'] . ' ' . $s['summary'] . ' ' . implode(' ', $s['includes']) . ' ' . $s['category']);
            $extra = count($s['rates']) - 1;
          ?>
          <article class="sv-card" data-id="<?= $s['id'] ?>" data-cat="<?= htmlspecialchars($s['category']) ?>" data-search="<?= htmlspecialchars($blob) ?>" data-name="<?= htmlspecialchars(strtolower($s['name'])) ?>" data-min="<?= $sortMin ?>" data-max="<?= $sortMax ?>" data-order="<?= $s['id'] ?>">
            <div class="sv-card-top">
              <span class="sv-icon"><i class="fa-solid <?= $categoryIcons[$s['category']] ?? 'fa-briefcase' ?>"></i></span>
              <?php if (!empty($r['est'])): ?>
                <span class="sv-tag">Estimate</span>
              <?php else: ?>
                <span class="sv-tag is-set">Fixed range</span>
              <?php endif; ?>
            </div>
            <div class="sv-card-body">
              <span class="sv-cat"><?= htmlspecialchars($s['category']) ?></span>
              <h3 class="sv-name"><?= htmlspecialchars($s['name']) ?></h3>
              <p class="sv-sum"><?= htmlspecialchars($s['summary']) ?></p>
            </div>
            <div class="sv-price">
              <div class="sv-price-l"><?= htmlspecialchars($r['label']) ?></div>
              <div class="sv-price-v"><?= htmlspecialchars(rateText($r)) ?></div>
              <div class="sv-price-u"><?= htmlspecialchars($r['unit']) ?></div>
              <?php if ($extra > 0): ?><div class="sv-price-m">+<?= $extra ?> more price<?= $extra > 1 ? 's' : '' ?></div><?php endif; ?>
            </div>
            <div class="sv-card-foot">
              <span>View details</span>
              <i class="fa-solid fa-arrow-right"></i>
            </div>
          </article>
        <?php endforeach; ?>
      </section>

      <div class="sv-empty" id="svEmpty"><i class="fa-regular fa-folder-open fs-2 d-block mb-2"></i>No services found.</div>

    </main>
  </div>
</div>

<div class="modal fade sv-modal" id="svModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <span class="sv-modal-cat" id="svModalCat"></span>
          <h2 class="sv-modal-title" id="svModalTitle"></h2>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="svModalBody"></div>
    </div>
  </div>
</div>

<script src="../assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script>
var DATA = <?= json_encode($jsData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
var activeCat = 'all';
var grid = document.getElementById('svGrid');
var searchInput = document.getElementById('svSearch');
var sortSelect = document.getElementById('svSort');
var modal = new bootstrap.Modal(document.getElementById('svModal'));

function esc(s) {
  var d = document.createElement('div');
  d.textContent = s;
  return d.innerHTML;
}

function applyFilters() {
  var q = searchInput.value.trim().toLowerCase();
  var shown = 0;
  var cards = Array.prototype.slice.call(grid.querySelectorAll('.sv-card'));

  cards.forEach(function (card) {
    var ok = (activeCat === 'all' || card.getAttribute('data-cat') === activeCat) && (card.getAttribute('data-search') || '').indexOf(q) !== -1;
    card.style.display = ok ? '' : 'none';
    if (ok) shown++;
  });

  var mode = sortSelect.value;
  cards.sort(function (a, b) {
    if (mode === 'name') return a.getAttribute('data-name').localeCompare(b.getAttribute('data-name'));
    if (mode === 'low') return Number(a.getAttribute('data-min')) - Number(b.getAttribute('data-min'));
    if (mode === 'high') return Number(b.getAttribute('data-max')) - Number(a.getAttribute('data-max'));
    return Number(a.getAttribute('data-order')) - Number(b.getAttribute('data-order'));
  });
  cards.forEach(function (card) { grid.appendChild(card); });

  document.getElementById('svCount').textContent = shown;
  document.getElementById('svEmpty').style.display = shown === 0 ? 'block' : 'none';
  grid.style.display = shown === 0 ? 'none' : '';
}

searchInput.addEventListener('input', applyFilters);
sortSelect.addEventListener('change', applyFilters);

document.querySelectorAll('.sv-chip').forEach(function (chip) {
  chip.addEventListener('click', function () {
    document.querySelectorAll('.sv-chip').forEach(function (c) { c.classList.remove('is-active'); });
    chip.classList.add('is-active');
    activeCat = chip.getAttribute('data-cat');
    applyFilters();
  });
});

function openModal(id) {
  var d = DATA[id];
  if (!d) return;

  document.getElementById('svModalCat').textContent = d.category;
  document.getElementById('svModalTitle').textContent = d.name;

  var html = '<p class="sv-modal-sum">' + esc(d.summary) + '</p>';

  html += '<h3 class="sv-sec first">Pricing</h3>';
  d.rates.forEach(function (r) {
    var tag = r.est ? '<span class="sv-tag">Estimate</span>' : '';
    html += '<div class="sv-rate-card"><div class="k">' + esc(r.label) + tag + '</div><div class="v">' + esc(r.text) + '</div><div class="u">' + esc(r.unit) + '</div></div>';
  });

  html += '<h3 class="sv-sec">What\'s included</h3><ul class="sv-inc">';
  d.includes.forEach(function (i) {
    html += '<li><i class="fa-solid fa-check"></i><span>' + esc(i) + '</span></li>';
  });
  html += '</ul>';

  if (d.note) {
    html += '<div class="sv-note"><i class="fa-solid fa-circle-exclamation mt-1"></i><span>' + esc(d.note) + '</span></div>';
  }

  document.getElementById('svModalBody').innerHTML = html;
  modal.show();
}

grid.addEventListener('click', function (e) {
  var card = e.target.closest('.sv-card');
  if (card) openModal(card.getAttribute('data-id'));
});
</script>

</body>
</html>