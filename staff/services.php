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
  --canvas: #F3F5F9;
  --card: #FFFFFF;
  --surface: #F6F8FB;
  --line: #E2E5EB;
  --line-strong: #D5DAE3;

  --ink: #1A2233;
  --ink-soft: #667085;
  --charcoal: #0F172A;
  --slate: #475569;

  --accent: #3B4E8A;
  --accent-hover: #2E3E70;
  --accent-soft: #E9ECF6;
  --accent-text: #2E3E70;

  --success-soft: #E3F3EC;
  --success-text: #0F5F49;
  --success-border: #BFE3D3;

  --warn-soft: #FBF0DD;
  --warn-text: #8A5A15;
  --warn-border: #EFD8A8;

  --danger-soft: #FAECE8;
  --danger-text: #93382A;
  --danger-border: #EDC7BC;
}

body {
  background-color: var(--canvas);
  color: var(--ink);
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  -webkit-font-smoothing: antialiased;
  overflow-x: hidden;
}

.dashboard-layout, .dashboard-main, .dashboard-content {
  background-color: var(--canvas) !important;
}
.dashboard-main { min-width: 0; max-width: 100%; }

.dashboard-title, h1, h2, h3, h4 { font-family: 'Lexend', 'Inter', sans-serif; }
.dashboard-title { color: var(--charcoal); letter-spacing: -0.01em; font-size: 1.15rem !important; }
.dashboard-subtitle { color: var(--ink-soft) !important; }
.dashboard-topbar {
  border-bottom: 1px solid var(--line) !important;
  background-color: var(--card);
  padding-top: .75rem;
  padding-bottom: .75rem;
}

.panel {
  background-color: var(--card);
  border: 1px solid var(--line);
  border-radius: 12px;
  box-shadow: 0 1px 2px rgba(42, 45, 47, .04);
}

.cat-summary {
  display: grid;
  grid-template-columns: minmax(240px, 1.4fr) repeat(4, minmax(0, 1fr));
  margin-bottom: 1rem;
  overflow: hidden;
}
.cat-intro {
  padding: 1.35rem 1.5rem;
  display: flex;
  flex-direction: column;
  justify-content: center;
  border-right: 1px solid var(--line);
}
.cat-intro-eyebrow {
  font-size: .66rem;
  font-weight: 700;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: var(--accent-text);
  margin-bottom: .35rem;
}
.cat-intro h2 {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--charcoal);
  letter-spacing: -0.01em;
  margin: 0 0 .25rem;
}
.cat-intro p { font-size: .8rem; color: var(--ink-soft); margin: 0; line-height: 1.5; }

.cat-metric {
  padding: 1.35rem 1rem;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  text-align: center;
  border-right: 1px solid var(--line);
  min-width: 0;
}
.cat-metric:last-child { border-right: none; }
.cat-metric-n {
  font-family: 'Lexend', sans-serif;
  font-size: 1.7rem;
  font-weight: 700;
  line-height: 1;
  color: var(--charcoal);
  font-variant-numeric: tabular-nums;
}
.cat-metric-l {
  margin-top: .5rem;
  font-size: .66rem;
  font-weight: 700;
  letter-spacing: .07em;
  text-transform: uppercase;
  color: var(--ink-soft);
}
.cat-metric.is-fixed .cat-metric-n { color: var(--success-text); }
.cat-metric.is-est .cat-metric-n { color: var(--warn-text); }

.toolbar { margin-bottom: 1rem; }
.toolbar-top {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: .65rem;
  padding: 1rem 1.15rem;
}
.search-box { position: relative; flex: 1 1 320px; max-width: 480px; }
.search-box i {
  position: absolute;
  left: 13px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--ink-soft);
  font-size: .85rem;
}
.search-box input, .sort-select {
  height: 40px;
  border: 1px solid var(--line-strong);
  border-radius: 8px;
  background-color: var(--card);
  font-size: .85rem;
  color: var(--ink);
  outline: none;
}
.search-box input { width: 100%; padding: 0 .9rem 0 2.2rem; }
.sort-select { padding: 0 2rem 0 .8rem; min-width: 190px; }
.search-box input:focus, .sort-select:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 .2rem rgba(59, 78, 138, .14);
}

.view-toggle {
  display: inline-flex;
  margin-left: auto;
  border: 1px solid var(--line-strong);
  border-radius: 8px;
  overflow: hidden;
  background-color: var(--card);
}
.view-toggle button {
  width: 40px;
  height: 38px;
  border: none;
  background: transparent;
  color: var(--ink-soft);
  font-size: .9rem;
  outline: none;
}
.view-toggle button + button { border-left: 1px solid var(--line-strong); }
.view-toggle button.is-active { background-color: var(--accent); color: #fff; }

.tabs-row {
  display: flex;
  gap: .25rem;
  flex-wrap: wrap;
  padding: 0 1.15rem;
  border-top: 1px solid var(--line);
  background-color: var(--surface);
  border-radius: 0 0 12px 12px;
}
.tab-btn {
  display: inline-flex;
  align-items: center;
  gap: .5rem;
  padding: .8rem .95rem;
  border: none;
  background: transparent;
  color: var(--slate);
  font-size: .8rem;
  font-weight: 600;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  outline: none;
  white-space: nowrap;
}
.tab-btn .tab-count {
  font-size: .68rem;
  font-weight: 700;
  padding: .1rem .45rem;
  border-radius: 999px;
  background-color: var(--line);
  color: var(--slate);
  font-variant-numeric: tabular-nums;
}
.tab-btn:hover { color: var(--accent-text); }
.tab-btn.is-active { color: var(--accent-text); border-bottom-color: var(--accent); }
.tab-btn.is-active .tab-count { background-color: var(--accent); color: #fff; }

.result-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: .75rem;
  font-size: .78rem;
  color: var(--ink-soft);
}
.result-meta strong { color: var(--ink); font-weight: 700; }

.svc-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 1rem;
}
.svc-card {
  display: flex;
  flex-direction: column;
  cursor: pointer;
  transition: border-color .15s, box-shadow .15s;
}
.svc-card:hover { border-color: var(--accent); box-shadow: 0 4px 14px rgba(42, 45, 47, .08); }
.svc-card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: .75rem;
  padding: 1rem 1.15rem .85rem;
}
.svc-cat {
  display: inline-flex;
  align-items: center;
  gap: .55rem;
  font-size: .68rem;
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: var(--ink-soft);
  min-width: 0;
}
.svc-cat-icon {
  width: 28px;
  height: 28px;
  border-radius: 7px;
  background-color: var(--accent-soft);
  color: var(--accent-text);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: .72rem;
  flex-shrink: 0;
}
.svc-cat-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.type-tag {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  font-size: .64rem;
  font-weight: 700;
  letter-spacing: .04em;
  text-transform: uppercase;
  padding: .24rem .55rem;
  border-radius: 6px;
  border: 1px solid transparent;
  white-space: nowrap;
}
.type-tag::before {
  content: '';
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background-color: currentColor;
}
.type-est { background-color: var(--warn-soft); color: var(--warn-text); border-color: var(--warn-border); }
.type-fixed { background-color: var(--success-soft); color: var(--success-text); border-color: var(--success-border); }

.svc-body { padding: 0 1.15rem 1rem; flex: 1 1 auto; }
.svc-name {
  font-size: 1rem;
  font-weight: 600;
  color: var(--charcoal);
  line-height: 1.35;
  margin: 0 0 .4rem;
}
.svc-sum { font-size: .8rem; color: var(--ink-soft); line-height: 1.55; margin: 0; }

.svc-rate {
  margin: 0 1.15rem;
  padding: .85rem 0;
  border-top: 1px solid var(--line);
}
.svc-rate-label {
  font-size: .64rem;
  font-weight: 700;
  letter-spacing: .07em;
  text-transform: uppercase;
  color: var(--ink-soft);
}
.svc-rate-value {
  font-family: 'Lexend', sans-serif;
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--accent-text);
  margin-top: .2rem;
  font-variant-numeric: tabular-nums;
}
.svc-rate-unit { font-size: .74rem; color: var(--slate); margin-top: .15rem; }
.svc-rate-more { font-size: .7rem; color: var(--ink-soft); margin-top: .25rem; }

.svc-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: .75rem 1.15rem;
  border-top: 1px solid var(--line);
  background-color: var(--surface);
  border-radius: 0 0 12px 12px;
  font-size: .78rem;
  font-weight: 600;
  color: var(--accent-text);
}
.svc-foot i { font-size: .72rem; transition: transform .15s; }
.svc-card:hover .svc-foot i { transform: translateX(3px); }

.svc-table-wrap { display: none; overflow: hidden; }
.svc-table-wrap.is-visible { display: block; }
.svc-table { margin: 0; width: 100%; }
.svc-table thead th {
  background-color: var(--surface) !important;
  color: var(--ink-soft);
  font-weight: 700;
  font-size: .68rem;
  letter-spacing: .07em;
  text-transform: uppercase;
  border-bottom: 1px solid var(--line) !important;
  padding: .75rem 1.15rem;
  white-space: nowrap;
}
.svc-table td {
  padding: .95rem 1.15rem;
  border-bottom: 1px solid var(--line);
  vertical-align: middle;
  color: var(--ink);
  background-color: transparent;
}
.svc-table tbody tr:last-child td { border-bottom: none; }
.svc-table tbody tr { cursor: pointer; transition: background-color .12s; }
.svc-table tbody tr:hover { background-color: var(--surface); }
.svc-table tbody tr:hover .row-chevron { color: var(--accent); transform: translateX(2px); }
.row-name { font-size: .88rem; font-weight: 600; color: var(--charcoal); }
.row-sum { font-size: .74rem; color: var(--ink-soft); margin-top: .15rem; max-width: 420px; }
.row-cat { display: inline-flex; align-items: center; gap: .5rem; font-size: .8rem; color: var(--slate); white-space: nowrap; }
.row-cat i { color: var(--accent-text); font-size: .75rem; }
.row-rate { font-family: 'Lexend', sans-serif; font-size: .9rem; font-weight: 700; color: var(--accent-text); white-space: nowrap; font-variant-numeric: tabular-nums; }
.row-unit { font-size: .74rem; color: var(--ink-soft); margin-top: .1rem; }
.row-chevron { color: #B6BECB; font-size: .78rem; transition: color .12s, transform .12s; }

.empty-panel {
  display: none;
  text-align: center;
  color: var(--ink-soft);
  padding: 3.5rem 1rem;
}
.empty-panel i { font-size: 2rem; color: #C5CED8; display: block; margin-bottom: .6rem; }
.empty-panel .empty-title { font-weight: 600; color: var(--ink); font-size: .95rem; }
.empty-panel .empty-text { font-size: .8rem; margin-top: .15rem; }

.svc-modal .modal-dialog { max-width: 1080px; }
.svc-modal .modal-content {
  border: none;
  border-radius: 16px;
  background-color: var(--card);
  overflow: hidden;
  box-shadow: 0 20px 50px rgba(15, 23, 42, .22);
}
.svc-modal .modal-header {
  border-bottom: 1px solid var(--line);
  background-color: var(--card);
  padding: 1.6rem 2rem;
  align-items: flex-start;
  gap: 1.1rem;
}
.modal-icon {
  width: 52px;
  height: 52px;
  border-radius: 12px;
  background-color: var(--accent);
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 1.15rem;
  flex-shrink: 0;
}
.modal-eyebrow-row { display: flex; align-items: center; flex-wrap: wrap; gap: .65rem; }
.modal-eyebrow {
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: var(--accent-text);
}
.modal-title-text {
  font-family: 'Lexend', 'Inter', sans-serif;
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--charcoal);
  letter-spacing: -0.015em;
  margin: .4rem 0 0;
  line-height: 1.3;
}
.modal-subtitle { font-size: .9rem; color: var(--ink-soft); margin: .35rem 0 0; line-height: 1.5; max-width: 640px; }
.svc-modal .btn-close {
  margin: 0 0 0 auto;
  width: 38px;
  height: 38px;
  padding: 0;
  background-size: 14px;
  opacity: .75;
  border-radius: 9px;
  flex-shrink: 0;
}
.svc-modal .btn-close:hover { background-color: var(--surface); opacity: 1; }
.svc-modal .btn-close:focus { box-shadow: none; }
.svc-modal .modal-body { padding: 1.75rem 2rem 2rem; background-color: var(--surface); }

.detail-panel {
  background-color: var(--card);
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 1.35rem 1.5rem;
}
.detail-panel + .detail-panel { margin-top: 1rem; }
.detail-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: .5rem;
  font-family: 'Inter', sans-serif;
  font-size: .72rem;
  font-weight: 700;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: var(--ink-soft);
  margin: 0 0 .95rem;
  padding-bottom: .75rem;
  border-bottom: 1px solid var(--line);
}
.detail-heading .heading-count {
  font-size: .68rem;
  font-weight: 700;
  letter-spacing: 0;
  padding: .12rem .55rem;
  border-radius: 999px;
  background-color: var(--accent-soft);
  color: var(--accent-text);
}
.detail-summary { font-size: .95rem; color: var(--slate); line-height: 1.7; margin: 0; }

.inc-list { list-style: none; padding: 0; margin: 0; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .65rem; }
.inc-list li {
  display: flex;
  gap: .75rem;
  align-items: center;
  font-size: .88rem;
  font-weight: 500;
  line-height: 1.4;
  color: var(--ink);
  padding: .75rem .9rem;
  background-color: var(--surface);
  border: 1px solid var(--line);
  border-radius: 10px;
}
.inc-list .inc-check {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background-color: var(--success-soft);
  color: var(--success-text);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: .62rem;
  flex-shrink: 0;
}

.rate-card {
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 1.15rem 1.25rem;
  background-color: var(--surface);
  margin-bottom: .75rem;
}
.rate-card:last-child { margin-bottom: 0; }
.rate-card-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: .5rem;
  font-size: .78rem;
  font-weight: 600;
  color: var(--ink-soft);
}
.rate-card-value {
  font-family: 'Lexend', sans-serif;
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--accent-text);
  margin-top: .45rem;
  font-variant-numeric: tabular-nums;
  line-height: 1.2;
  letter-spacing: -0.01em;
}
.rate-card-unit { font-size: .8rem; color: var(--slate); margin-top: .3rem; line-height: 1.45; }

.fact-list { margin: 0; }
.fact-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  padding: .7rem 0;
  border-bottom: 1px solid var(--line);
  font-size: .85rem;
}
.fact-row:last-child { border-bottom: none; padding-bottom: 0; }
.fact-row:first-child { padding-top: 0; }
.fact-row dt { font-weight: 500; color: var(--ink-soft); margin: 0; }
.fact-row dd { font-weight: 600; color: var(--ink); margin: 0; text-align: right; }

.note-box {
  display: flex;
  gap: .75rem;
  background-color: var(--danger-soft);
  border: 1px solid var(--danger-border);
  color: var(--danger-text);
  border-radius: 12px;
  padding: 1rem 1.15rem;
  font-size: .85rem;
  line-height: 1.5;
}
.note-box i { margin-top: .2rem; }
.note-box strong { display: block; font-size: .72rem; letter-spacing: .06em; text-transform: uppercase; margin-bottom: .15rem; }

.modal-side { position: sticky; top: 0; }

@media (max-width: 1199.98px) {
  .cat-summary { grid-template-columns: repeat(4, minmax(0, 1fr)); }
  .cat-intro { grid-column: 1 / -1; border-right: none; border-bottom: 1px solid var(--line); }
}

@media (max-width: 991.98px) {
  .col-hide-lg { display: none; }
}

@media (max-width: 767.98px) {
  .dashboard-content { padding: .75rem !important; }
  .dashboard-title { font-size: 1rem !important; }

  .cat-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .cat-intro { padding: 1.1rem 1.15rem; }
  .cat-metric { padding: 1rem .75rem; border-bottom: 1px solid var(--line); }
  .cat-metric:nth-child(odd) { border-right: 1px solid var(--line); }
  .cat-metric:nth-child(even) { border-right: none; }
  .cat-metric:nth-last-child(-n+2) { border-bottom: none; }
  .cat-metric-n { font-size: 1.4rem; }

  .toolbar-top { padding: .85rem; }
  .search-box { max-width: none; flex: 1 1 100%; }
  .sort-select { flex: 1 1 auto; min-width: 0; }
  .view-toggle { margin-left: 0; }
  .tabs-row { padding: 0 .5rem; flex-wrap: nowrap; overflow-x: auto; scrollbar-width: none; }
  .tabs-row::-webkit-scrollbar { display: none; }

  .svc-grid { grid-template-columns: 1fr; }
  .svc-table thead th, .svc-table td { padding: .75rem .85rem; }

  .svc-modal .modal-header { padding: 1.1rem 1.1rem; gap: .8rem; }
  .modal-icon { width: 42px; height: 42px; font-size: 1rem; border-radius: 10px; }
  .svc-modal .modal-body { padding: 1rem; }
  .modal-title-text { font-size: 1.1rem; }
  .modal-subtitle { font-size: .8rem; }
  .detail-panel { padding: 1.1rem 1.1rem; }
  .inc-list { grid-template-columns: 1fr; }
  .rate-card-value { font-size: 1.45rem; }
  .modal-side { position: static; }
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

    <header class="dashboard-topbar d-flex align-items-center justify-content-between px-3 px-md-4">
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

      <section class="panel cat-summary">
        <div class="cat-intro">
          <span class="cat-intro-eyebrow">Rate Reference</span>
          <h2>Service Catalog</h2>
          <p>Standard services and rate ranges offered to clients.</p>
        </div>
        <div class="cat-metric"><span class="cat-metric-n"><?= $totalServices ?></span><span class="cat-metric-l">Services</span></div>
        <div class="cat-metric"><span class="cat-metric-n"><?= count($categories) ?></span><span class="cat-metric-l">Categories</span></div>
        <div class="cat-metric is-fixed"><span class="cat-metric-n"><?= $totalServices - $estCount ?></span><span class="cat-metric-l">Fixed Range</span></div>
        <div class="cat-metric is-est"><span class="cat-metric-n"><?= $estCount ?></span><span class="cat-metric-l">Estimated</span></div>
      </section>

      <section class="panel toolbar">
        <div class="toolbar-top">
          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="svcSearch" placeholder="Search services, inclusions or category" autocomplete="off">
          </div>
          <select id="svcSort" class="sort-select" aria-label="Sort services">
            <option value="default">Sort: Default</option>
            <option value="name">Name A-Z</option>
            <option value="low">Lowest rate first</option>
            <option value="high">Highest rate first</option>
          </select>
          <div class="view-toggle" role="group" aria-label="Change view">
            <button type="button" id="viewGrid" class="is-active" aria-label="Card view" title="Card view"><i class="fa-solid fa-table-cells-large"></i></button>
            <button type="button" id="viewTable" aria-label="Table view" title="Table view"><i class="fa-solid fa-list"></i></button>
          </div>
        </div>
        <div class="tabs-row" id="svcTabs">
          <button type="button" class="tab-btn is-active" data-cat="all">All Services<span class="tab-count"><?= $totalServices ?></span></button>
          <?php foreach ($categories as $cat => $n): ?>
            <button type="button" class="tab-btn" data-cat="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?><span class="tab-count"><?= $n ?></span></button>
          <?php endforeach; ?>
        </div>
      </section>

      <div class="result-meta">
        <span>Showing <strong id="svcCount"><?= $totalServices ?></strong> of <strong><?= $totalServices ?></strong> services</span>
      </div>

      <section class="svc-grid" id="svcGrid">
        <?php foreach ($services as $s): ?>
          <?php
            $r = $s['rates'][0];
            $isPeso = $r['fmt'] === 'peso';
            $sortMin = $isPeso ? $r['min'] : -1;
            $sortMax = $isPeso ? ($r['max'] ?? $r['min']) : -1;
            $blob = strtolower($s['name'] . ' ' . $s['summary'] . ' ' . implode(' ', $s['includes']) . ' ' . $s['category']);
            $extra = count($s['rates']) - 1;
            $icon = $categoryIcons[$s['category']] ?? 'fa-briefcase';
          ?>
          <article class="panel svc-card" data-id="<?= $s['id'] ?>" data-cat="<?= htmlspecialchars($s['category']) ?>" data-search="<?= htmlspecialchars($blob) ?>" data-name="<?= htmlspecialchars(strtolower($s['name'])) ?>" data-min="<?= $sortMin ?>" data-max="<?= $sortMax ?>" data-order="<?= $s['id'] ?>">
            <div class="svc-card-head">
              <span class="svc-cat">
                <span class="svc-cat-icon"><i class="fa-solid <?= $icon ?>"></i></span>
                <span class="svc-cat-text"><?= htmlspecialchars($s['category']) ?></span>
              </span>
              <?php if (!empty($r['est'])): ?>
                <span class="type-tag type-est">Estimate</span>
              <?php else: ?>
                <span class="type-tag type-fixed">Fixed range</span>
              <?php endif; ?>
            </div>
            <div class="svc-body">
              <h3 class="svc-name"><?= htmlspecialchars($s['name']) ?></h3>
              <p class="svc-sum"><?= htmlspecialchars($s['summary']) ?></p>
            </div>
            <div class="svc-rate">
              <div class="svc-rate-label"><?= htmlspecialchars($r['label']) ?></div>
              <div class="svc-rate-value"><?= htmlspecialchars(rateText($r)) ?></div>
              <div class="svc-rate-unit"><?= htmlspecialchars($r['unit']) ?></div>
              <?php if ($extra > 0): ?><div class="svc-rate-more">+<?= $extra ?> more price<?= $extra > 1 ? 's' : '' ?></div><?php endif; ?>
            </div>
            <div class="svc-foot">
              <span>View details</span>
              <i class="fa-solid fa-arrow-right"></i>
            </div>
          </article>
        <?php endforeach; ?>
      </section>

      <section class="panel svc-table-wrap" id="svcTableWrap">
        <div class="table-responsive">
          <table class="table svc-table align-middle">
            <thead>
              <tr>
                <th scope="col">Service</th>
                <th scope="col" class="d-none d-md-table-cell">Category</th>
                <th scope="col" class="d-none d-lg-table-cell">Pricing Type</th>
                <th scope="col">Rate</th>
                <th scope="col" style="width:36px;"></th>
              </tr>
            </thead>
            <tbody id="svcTableBody">
              <?php foreach ($services as $s): ?>
                <?php
                  $r = $s['rates'][0];
                  $isPeso = $r['fmt'] === 'peso';
                  $sortMin = $isPeso ? $r['min'] : -1;
                  $sortMax = $isPeso ? ($r['max'] ?? $r['min']) : -1;
                  $blob = strtolower($s['name'] . ' ' . $s['summary'] . ' ' . implode(' ', $s['includes']) . ' ' . $s['category']);
                  $icon = $categoryIcons[$s['category']] ?? 'fa-briefcase';
                ?>
                <tr class="svc-row" data-id="<?= $s['id'] ?>" data-cat="<?= htmlspecialchars($s['category']) ?>" data-search="<?= htmlspecialchars($blob) ?>" data-name="<?= htmlspecialchars(strtolower($s['name'])) ?>" data-min="<?= $sortMin ?>" data-max="<?= $sortMax ?>" data-order="<?= $s['id'] ?>">
                  <td>
                    <div class="row-name"><?= htmlspecialchars($s['name']) ?></div>
                    <div class="row-sum"><?= htmlspecialchars($s['summary']) ?></div>
                  </td>
                  <td class="d-none d-md-table-cell"><span class="row-cat"><i class="fa-solid <?= $icon ?>"></i><?= htmlspecialchars($s['category']) ?></span></td>
                  <td class="d-none d-lg-table-cell">
                    <?php if (!empty($r['est'])): ?>
                      <span class="type-tag type-est">Estimate</span>
                    <?php else: ?>
                      <span class="type-tag type-fixed">Fixed range</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="row-rate"><?= htmlspecialchars(rateText($r)) ?></div>
                    <div class="row-unit"><?= htmlspecialchars($r['label']) ?></div>
                  </td>
                  <td class="text-end"><i class="fa-solid fa-chevron-right row-chevron"></i></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <div class="panel empty-panel" id="svcEmpty">
        <i class="fa-regular fa-folder-open"></i>
        <div class="empty-title">No services found</div>
        <div class="empty-text">Try a different keyword or category.</div>
      </div>

    </main>
  </div>
</div>

<div class="modal fade svc-modal" id="svcModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <span class="modal-icon"><i class="fa-solid fa-briefcase" id="svcModalIcon"></i></span>
        <div style="min-width:0; flex:1 1 auto;">
          <div class="modal-eyebrow-row">
            <span class="modal-eyebrow" id="svcModalCat"></span>
            <span id="svcModalTag"></span>
          </div>
          <h2 class="modal-title-text" id="svcModalTitle"></h2>
          <p class="modal-subtitle" id="svcModalSub"></p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="svcModalBody"></div>
    </div>
  </div>
</div>

<script src="../assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script>
var DATA = <?= json_encode($jsData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
var ICONS = <?= json_encode($categoryIcons, JSON_UNESCAPED_UNICODE) ?>;
var activeCat = 'all';
var grid = document.getElementById('svcGrid');
var tableWrap = document.getElementById('svcTableWrap');
var tableBody = document.getElementById('svcTableBody');
var searchInput = document.getElementById('svcSearch');
var sortSelect = document.getElementById('svcSort');
var viewGridBtn = document.getElementById('viewGrid');
var viewTableBtn = document.getElementById('viewTable');
var currentView = 'grid';
var modal = new bootstrap.Modal(document.getElementById('svcModal'));

function esc(value) {
  var d = document.createElement('div');
  d.textContent = value;
  return d.innerHTML;
}

function sortItems(items, mode) {
  return items.sort(function (a, b) {
    if (mode === 'name') return a.getAttribute('data-name').localeCompare(b.getAttribute('data-name'));
    if (mode === 'low') return Number(a.getAttribute('data-min')) - Number(b.getAttribute('data-min'));
    if (mode === 'high') return Number(b.getAttribute('data-max')) - Number(a.getAttribute('data-max'));
    return Number(a.getAttribute('data-order')) - Number(b.getAttribute('data-order'));
  });
}

function matches(item, query) {
  var inCat = activeCat === 'all' || item.getAttribute('data-cat') === activeCat;
  return inCat && (item.getAttribute('data-search') || '').indexOf(query) !== -1;
}

function applyFilters() {
  var query = searchInput.value.trim().toLowerCase();
  var mode = sortSelect.value;
  var cards = Array.prototype.slice.call(grid.querySelectorAll('.svc-card'));
  var rows = Array.prototype.slice.call(tableBody.querySelectorAll('.svc-row'));
  var shown = 0;

  cards.forEach(function (card) {
    var ok = matches(card, query);
    card.style.display = ok ? '' : 'none';
    if (ok) shown++;
  });
  rows.forEach(function (row) {
    row.style.display = matches(row, query) ? '' : 'none';
  });

  sortItems(cards, mode).forEach(function (card) { grid.appendChild(card); });
  sortItems(rows, mode).forEach(function (row) { tableBody.appendChild(row); });

  document.getElementById('svcCount').textContent = shown;
  document.getElementById('svcEmpty').style.display = shown === 0 ? 'block' : 'none';
  grid.style.display = (shown === 0 || currentView !== 'grid') ? 'none' : '';
  tableWrap.classList.toggle('is-visible', shown !== 0 && currentView === 'table');
}

function setView(view) {
  currentView = view;
  viewGridBtn.classList.toggle('is-active', view === 'grid');
  viewTableBtn.classList.toggle('is-active', view === 'table');
  applyFilters();
}

searchInput.addEventListener('input', applyFilters);
sortSelect.addEventListener('change', applyFilters);
viewGridBtn.addEventListener('click', function () { setView('grid'); });
viewTableBtn.addEventListener('click', function () { setView('table'); });

document.querySelectorAll('.tab-btn').forEach(function (tab) {
  tab.addEventListener('click', function () {
    document.querySelectorAll('.tab-btn').forEach(function (t) { t.classList.remove('is-active'); });
    tab.classList.add('is-active');
    activeCat = tab.getAttribute('data-cat');
    applyFilters();
  });
});

function openModal(id) {
  var d = DATA[id];
  if (!d) return;

  var firstRate = d.rates[0];
  var isEst = firstRate && firstRate.est;

  document.getElementById('svcModalCat').textContent = d.category;
  document.getElementById('svcModalIcon').className = 'fa-solid ' + (ICONS[d.category] || 'fa-briefcase');
  document.getElementById('svcModalTitle').textContent = d.name;
  document.getElementById('svcModalSub').textContent = d.summary;
  document.getElementById('svcModalTag').innerHTML = isEst ? '<span class="type-tag type-est">Estimate</span>' : '<span class="type-tag type-fixed">Fixed range</span>';

  var main = '<div class="detail-panel">';
  main += '<h3 class="detail-heading"><span>Overview</span></h3><p class="detail-summary">' + esc(d.summary) + '</p></div>';

  main += '<div class="detail-panel"><h3 class="detail-heading"><span>What\'s included</span><span class="heading-count">' + d.includes.length + ' items</span></h3><ul class="inc-list">';
  d.includes.forEach(function (item) {
    main += '<li><span class="inc-check"><i class="fa-solid fa-check"></i></span><span>' + esc(item) + '</span></li>';
  });
  main += '</ul></div>';

  if (d.note) {
    main += '<div class="note-box" style="margin-top:1rem;"><i class="fa-solid fa-circle-exclamation"></i><div><strong>Please note</strong>' + esc(d.note) + '</div></div>';
  }

  var side = '<div class="modal-side"><div class="detail-panel"><h3 class="detail-heading"><span>Pricing</span></h3>';
  d.rates.forEach(function (rate) {
    side += '<div class="rate-card"><div class="rate-card-top"><span>' + esc(rate.label) + '</span></div><div class="rate-card-value">' + esc(rate.text) + '</div><div class="rate-card-unit">' + esc(rate.unit) + '</div></div>';
  });
  side += '</div>';

  side += '<div class="detail-panel"><h3 class="detail-heading"><span>Quick facts</span></h3><dl class="fact-list">';
  side += '<div class="fact-row"><dt>Category</dt><dd>' + esc(d.category) + '</dd></div>';
  side += '<div class="fact-row"><dt>Pricing type</dt><dd>' + (isEst ? 'Estimate' : 'Fixed range') + '</dd></div>';
  side += '<div class="fact-row"><dt>Inclusions</dt><dd>' + d.includes.length + '</dd></div>';
  side += '<div class="fact-row"><dt>Rate options</dt><dd>' + d.rates.length + '</dd></div>';
  side += '</dl></div></div>';

  document.getElementById('svcModalBody').innerHTML = '<div class="row g-3 g-lg-4"><div class="col-lg-8">' + main + '</div><div class="col-lg-4">' + side + '</div></div>';
  modal.show();
}

grid.addEventListener('click', function (e) {
  var card = e.target.closest('.svc-card');
  if (card) openModal(card.getAttribute('data-id'));
});

tableBody.addEventListener('click', function (e) {
  var row = e.target.closest('.svc-row');
  if (row) openModal(row.getAttribute('data-id'));
});
</script>

</body>
</html>