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
        'category' => 'BIR and Taxes',
        'name' => 'BIR Help (Notice, Update, Closure)',
        'summary' => 'Assistance when the BIR sends a notice or when registration needs changes.',
        'includes' => ['Response to BIR notice', 'Registration update', 'Business closure or transfer', 'Compliance check'],
        'rates' => [['label' => 'Service fee', 'min' => 2000, 'max' => 15000, 'unit' => 'per case', 'fmt' => 'peso', 'est' => true]],
        'note' => '',
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
        'category' => 'Legal and Documents',
        'name' => 'Paralegal (Legal Papers)',
        'summary' => 'Assistance in drafting and reviewing legal documents.',
        'includes' => ['Contract drafting', 'Contract review', 'Labor law assistance', 'Document filing'],
        'rates' => [['label' => 'Service fee', 'min' => 3000, 'max' => 25000, 'unit' => 'per job', 'fmt' => 'peso']],
        'note' => 'This is not a substitute for a lawyer.',
    ],
    [
        'category' => 'Legal and Documents',
        'name' => 'Corporate Documents',
        'summary' => 'Preparation and updating of corporate papers.',
        'includes' => ['Meeting minutes', 'Corporate records', 'GIS (General Information Sheet)', 'Amendment of Articles and By-Laws'],
        'rates' => [['label' => 'Service fee', 'min' => 5000, 'max' => 30000, 'unit' => 'depends on the volume of work', 'fmt' => 'peso']],
        'note' => '',
    ],
    [
        'category' => 'Cooperatives and Agri',
        'name' => 'CDA Cooperative Registration',
        'summary' => 'Registration of a cooperative with the CDA.',
        'includes' => ['Bylaws', 'Articles of Cooperation', 'Pre-registration seminar', 'CDA filing'],
        'rates' => [['label' => 'Service fee', 'min' => 10000, 'max' => 40000, 'unit' => 'per registration', 'fmt' => 'peso', 'est' => true]],
        'note' => 'CDA fees are separate.',
    ],
    [
        'category' => 'Cooperatives and Agri',
        'name' => 'Agri-Business Plan',
        'summary' => 'Plan for farming, processing, or trading of products.',
        'includes' => ['Market study', 'Cost and income (projection)', 'Operations plan', 'For loan or grant'],
        'rates' => [['label' => 'Service fee', 'min' => 8000, 'max' => 35000, 'unit' => 'per plan', 'fmt' => 'peso', 'est' => true]],
        'note' => '',
    ],
    [
        'category' => 'Cooperatives and Agri',
        'name' => 'Loan Application Papers',
        'summary' => 'Preparation of papers for a loan with a bank or agency.',
        'includes' => ['Business plan', 'Financial statements', 'Checklist of requirements', 'Application guidance'],
        'rates' => [['label' => 'Service fee', 'min' => 3000, 'max' => 15000, 'unit' => 'per application', 'fmt' => 'peso', 'est' => true]],
        'note' => 'Approval of the loan is not guaranteed.',
    ],
    [
        'category' => 'HR and Training',
        'name' => 'HR Setup',
        'summary' => 'Setting up the system for employees.',
        'includes' => ['Time and attendance', 'Payroll', 'Leave management', 'Recruitment', 'Performance review', 'Labor compliance'],
        'rates' => [['label' => 'Service fee', 'min' => 5000, 'max' => 40000, 'unit' => 'depends on company size', 'fmt' => 'peso']],
        'note' => '',
    ],
    [
        'category' => 'HR and Training',
        'name' => 'Training and Workshops',
        'summary' => 'Training for employees and organizations.',
        'includes' => ['Leadership and soft skills', 'HRIS and compliance', 'Onsite, Zoom, or hybrid'],
        'rates' => [['label' => 'Training fee', 'min' => 3000, 'max' => 30000, 'unit' => 'per session, depends on the number of participants', 'fmt' => 'peso']],
        'note' => '',
    ],
    [
        'category' => 'Business Advisory',
        'name' => 'Business Consultation',
        'summary' => 'Advice on how to grow and improve the business.',
        'includes' => ['Business planning', 'Startup advice', 'Process improvement', 'Project management'],
        'rates' => [['label' => 'Service fee', 'min' => 1000, 'max' => 20000, 'unit' => 'per session or project', 'fmt' => 'peso', 'est' => true]],
        'note' => '',
    ],
    [
        'category' => 'Business Advisory',
        'name' => 'Feasibility Study and Research',
        'summary' => 'Study on whether the business will be profitable before starting.',
        'includes' => ['Business research', 'Feasibility study', 'Market trends', 'Organization check'],
        'rates' => [['label' => 'Study fee', 'min' => 10000, 'max' => 60000, 'unit' => 'higher for more in-depth studies', 'fmt' => 'peso']],
        'note' => '',
    ],
    [
        'category' => 'Business Advisory',
        'name' => 'Business Buy and Sell',
        'summary' => 'Assistance in buying, selling, or transferring a business.',
        'includes' => ['Business valuation', 'Finding an investor', 'Handling the sale', 'Document check'],
        'rates' => [['label' => 'Commission', 'min' => 2, 'max' => 5, 'unit' => 'of the sale value', 'fmt' => 'percent', 'est' => true]],
        'note' => '',
    ],
    [
        'category' => 'Marketing and IT',
        'name' => 'Marketing and Social Media',
        'summary' => 'Promoting and growing the business online and offline.',
        'includes' => ['Branding', 'Social media management', 'Customer retention', 'Competitor study'],
        'rates' => [['label' => 'Fee per campaign', 'min' => 5000, 'max' => 50000, 'unit' => 'per campaign', 'fmt' => 'peso']],
        'note' => '',
    ],
    [
        'category' => 'Marketing and IT',
        'name' => 'IT Systems',
        'summary' => 'Building and maintaining business systems.',
        'includes' => ['System development', 'Digital processes', 'Maintenance and support'],
        'rates' => [
            ['label' => 'System development', 'min' => 15000, 'max' => null, 'unit' => 'starting', 'fmt' => 'peso'],
            ['label' => 'Monthly maintenance', 'min' => 1500, 'max' => 6000, 'unit' => 'per month', 'fmt' => 'peso'],
        ],
        'note' => '',
    ],
    [
        'category' => 'Marketing and IT',
        'name' => 'Online Store',
        'summary' => 'Building an online shop to sell on the internet.',
        'includes' => ['Online shop setup', 'Product listing', 'Order processing', 'Secure online payment'],
        'rates' => [['label' => 'Setup fee', 'min' => 10000, 'max' => 60000, 'unit' => 'per project', 'fmt' => 'peso', 'est' => true]],
        'note' => '',
    ],
    [
        'category' => 'Real Estate and Rentals',
        'name' => 'Real Estate Broker',
        'summary' => 'Assistance in buying, selling, and leasing land or houses.',
        'includes' => ['Listing and promotion', 'Finding a buyer or tenant', 'Negotiation', 'Support until closing'],
        'rates' => [['label' => 'Commission', 'min' => 3, 'max' => 5, 'unit' => 'of the sale price', 'fmt' => 'percent', 'est' => true]],
        'note' => '',
    ],
    [
        'category' => 'Real Estate and Rentals',
        'name' => 'Property Management',
        'summary' => 'Management of rental houses, apartments, or commercial spaces.',
        'includes' => ['Tenant coordination', 'Rent collection', 'Maintenance', 'Occupancy check'],
        'rates' => [['label' => 'Management fee', 'min' => 8, 'max' => 15, 'unit' => 'of the rent collected', 'fmt' => 'percent', 'est' => true]],
        'note' => '',
    ],
    [
        'category' => 'Real Estate and Rentals',
        'name' => 'Transient and Vacation Rental',
        'summary' => 'Management of transient, homestay, and vacation rentals.',
        'includes' => ['Online booking', 'Guest coordination', 'Cleaning and maintenance', 'Promotion'],
        'rates' => [['label' => 'Management fee', 'min' => 15, 'max' => 25, 'unit' => 'of booking revenue', 'fmt' => 'percent', 'est' => true]],
        'note' => '',
    ],
    [
        'category' => 'Real Estate and Rentals',
        'name' => 'Recreation Venue Management',
        'summary' => 'Management of sports and recreation venues, such as paddle parks.',
        'includes' => ['Venue administration', 'Booking', 'Customer service', 'Event coordination'],
        'rates' => [['label' => 'Management fee', 'min' => 5000, 'max' => 30000, 'unit' => 'per month', 'fmt' => 'peso', 'est' => true]],
        'note' => '',
    ],
    [
        'category' => 'Real Estate and Rentals',
        'name' => 'Vehicle Rental',
        'summary' => 'Vehicle rental for personal, business, or group use.',
        'includes' => ['Short-term rental', 'Long-term rental', 'Special trips', 'Online reservation'],
        'rates' => [['label' => 'Rental price', 'min' => 1000, 'max' => 3500, 'unit' => 'per day', 'fmt' => 'peso', 'est' => true]],
        'note' => '',
    ],
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
  --indigo: #3B4E8A;
  --indigo-dark: #2E3E70;
  --indigo-soft: #EEF0F8;
  --slate: #475569;
  --ink: #1A2233;
  --muted: #667085;
  --line: #E5E7EB;
  --line-soft: #F1F2F5;
  --green: #1F7A4D;
  --amber: #9A6A12;
  --amber-soft: #FBF3E0;
}
body { background: #fff; color: var(--ink); font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; overflow-x: hidden; }
.dashboard-layout, .dashboard-main, .dashboard-content { background: #fff !important; }
.dashboard-main { min-width: 0; max-width: 100%; }
.dashboard-title, h1, h2, h3, h4 { font-family: 'Lexend', 'Inter', sans-serif; }
.dashboard-title { color: var(--navy); letter-spacing: -0.01em; }
.dashboard-subtitle { color: var(--muted) !important; }
.dashboard-topbar { border-bottom: 1px solid var(--line) !important; background: #fff; }

.sv-strip { display: grid; grid-template-columns: repeat(4, 1fr); border: 1px solid var(--line); border-radius: 10px; margin-bottom: 1rem; overflow: hidden; }
.sv-strip-item { padding: .8rem 1.1rem; border-right: 1px solid var(--line); display: flex; align-items: baseline; gap: .6rem; min-width: 0; }
.sv-strip-item:last-child { border-right: none; }
.sv-strip-n { font-family: 'Lexend', sans-serif; font-size: 1.35rem; font-weight: 700; color: var(--navy); }
.sv-strip-l { font-size: .72rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--muted); }

.sv-bar { display: flex; flex-wrap: wrap; gap: .6rem; align-items: center; margin-bottom: .75rem; }
.sv-search { position: relative; flex: 1 1 320px; max-width: 520px; }
.sv-search i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: .85rem; }
.sv-search input, .sv-select { height: 40px; border: 1px solid var(--line); border-radius: 8px; background: #fff; font-size: .85rem; color: var(--ink); outline: none; }
.sv-search input { width: 100%; padding: 0 .9rem 0 2.2rem; }
.sv-select { padding: 0 2rem 0 .8rem; min-width: 190px; }
.sv-search input:focus, .sv-select:focus { border-color: var(--indigo); box-shadow: 0 0 0 .2rem rgba(59,78,138,.12); }
.sv-shown { margin-left: auto; font-size: .78rem; color: var(--muted); }

.sv-chips { display: flex; gap: .4rem; flex-wrap: wrap; margin-bottom: 1rem; }
.sv-chip { border: 1px solid var(--line); background: #fff; color: var(--slate); border-radius: 8px; padding: .35rem .8rem; font-size: .76rem; font-weight: 600; cursor: pointer; transition: all .12s ease; }
.sv-chip:hover { border-color: var(--indigo); color: var(--indigo-dark); }
.sv-chip.is-active { background: var(--indigo); border-color: var(--indigo); color: #fff; }
.sv-chip b { font-weight: 500; opacity: .65; margin-left: .3rem; }

.sv-legend { display: flex; align-items: center; gap: .5rem; font-size: .74rem; color: var(--muted); margin-bottom: .75rem; }

.sv-tablewrap { border: 1px solid var(--line); border-radius: 10px; background: #fff; overflow: hidden; }
.sv-table { width: 100%; border-collapse: collapse; }
.sv-table thead th { background: #FAFAFB; border-bottom: 1px solid var(--line); padding: .7rem 1rem; font-size: .68rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--muted); white-space: nowrap; text-align: left; }
.sv-table th.num, .sv-table td.num { text-align: right; }
.sv-table tbody tr { border-bottom: 1px solid var(--line-soft); cursor: pointer; transition: background-color .1s ease; }
.sv-table tbody tr:last-child { border-bottom: none; }
.sv-table tbody tr:hover { background: #F8F9FC; }
.sv-table tbody tr.is-selected { background: var(--indigo-soft); }
.sv-table td { padding: .8rem 1rem; vertical-align: middle; font-size: .84rem; }
.sv-name { font-weight: 600; color: var(--navy); }
.sv-sum { font-size: .75rem; color: var(--muted); margin-top: .1rem; }
.sv-cat { display: inline-block; background: var(--indigo-soft); color: var(--indigo-dark); border-radius: 6px; padding: .2rem .55rem; font-size: .72rem; font-weight: 600; }
.sv-money { font-family: 'Lexend', sans-serif; font-weight: 600; color: var(--indigo-dark); font-variant-numeric: tabular-nums; white-space: nowrap; }
.sv-dash { color: #98A0AE; font-size: .8rem; }
.sv-unit { font-size: .75rem; color: var(--muted); }
.sv-est { display: inline-block; margin-left: .4rem; background: var(--amber-soft); color: var(--amber); font-size: .6rem; font-weight: 700; letter-spacing: .04em; border-radius: 4px; padding: .05rem .35rem; vertical-align: middle; }
.sv-more { color: var(--muted); font-size: .68rem; display: block; }
.sv-empty { display: none; text-align: center; color: var(--muted); padding: 3rem 1rem; }

.sv-overlay { position: fixed; inset: 0; background: rgba(15,23,42,.35); opacity: 0; pointer-events: none; transition: opacity .2s ease; z-index: 1040; }
.sv-overlay.is-open { opacity: 1; pointer-events: auto; }
.sv-panel { position: fixed; top: 0; right: 0; bottom: 0; width: 460px; max-width: 100%; background: #fff; box-shadow: -8px 0 30px rgba(15,23,42,.12); transform: translateX(100%); transition: transform .25s ease; z-index: 1050; display: flex; flex-direction: column; }
.sv-panel.is-open { transform: translateX(0); }
.sv-panel-head { padding: 1.1rem 1.25rem; border-bottom: 1px solid var(--line); display: flex; gap: 1rem; align-items: flex-start; }
.sv-panel-title { font-size: 1.05rem; font-weight: 700; color: var(--navy); margin: .35rem 0 0; }
.sv-close { margin-left: auto; border: none; background: transparent; width: 32px; height: 32px; border-radius: 8px; color: var(--muted); flex-shrink: 0; }
.sv-close:hover { background: var(--line-soft); color: var(--ink); }
.sv-panel-body { padding: 1.25rem; overflow-y: auto; flex: 1 1 auto; }
.sv-panel-sum { font-size: .85rem; color: var(--slate); margin-bottom: 1.25rem; line-height: 1.5; }
.sv-sec { font-family: 'Inter', sans-serif; font-size: .68rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); margin: 1.25rem 0 .6rem; }
.sv-rate-card { border: 1px solid var(--line); border-radius: 10px; padding: .8rem 1rem; margin-bottom: .6rem; }
.sv-rate-card .k { font-size: .72rem; font-weight: 600; color: var(--muted); }
.sv-rate-card .v { font-family: 'Lexend', sans-serif; font-size: 1.3rem; font-weight: 700; color: var(--indigo-dark); margin-top: .1rem; }
.sv-rate-card .u { font-size: .75rem; color: var(--slate); margin-top: .1rem; }
.sv-inc { list-style: none; padding: 0; margin: 0; display: grid; gap: .5rem; }
.sv-inc li { display: flex; gap: .6rem; font-size: .84rem; line-height: 1.4; }
.sv-inc i { color: var(--green); font-size: .72rem; margin-top: .3rem; }
.sv-warn { display: flex; gap: .6rem; background: #FDF1EE; color: #8A3B2B; border-radius: 8px; padding: .65rem .8rem; font-size: .8rem; margin-top: 1rem; }
.sv-estnote { display: flex; gap: .6rem; background: var(--amber-soft); color: var(--amber); border-radius: 8px; padding: .65rem .8rem; font-size: .78rem; margin-bottom: .8rem; }

@media (max-width: 991.98px) {
  .sv-strip { grid-template-columns: repeat(2, 1fr); }
  .sv-strip-item:nth-child(2) { border-right: none; }
  .sv-strip-item:nth-child(-n+2) { border-bottom: 1px solid var(--line); }
  .sv-table th:nth-child(2), .sv-table td:nth-child(2) { display: none; }
}

@media (max-width: 767.98px) {
  .dashboard-content { padding: .75rem !important; }
  .dashboard-title { font-size: .92rem !important; }
  .sv-search { max-width: none; flex: 1 1 100%; }
  .sv-select { flex: 1 1 100%; width: 100%; }
  .sv-shown { margin-left: 0; width: 100%; }
  .sv-chips { flex-wrap: nowrap; overflow-x: auto; padding-bottom: .3rem; scrollbar-width: none; }
  .sv-chips::-webkit-scrollbar { display: none; }
  .sv-chip { flex: 0 0 auto; white-space: nowrap; }

  .sv-tablewrap { border: none; border-radius: 0; overflow: visible; }
  .sv-table, .sv-table tbody { display: block; }
  .sv-table thead { display: none; }
  .sv-table tbody tr { display: block; border: 1px solid var(--line); border-radius: 10px; padding: .85rem .95rem; margin-bottom: .6rem; }
  .sv-table tbody tr:last-child { border-bottom: 1px solid var(--line); }
  .sv-table td { display: block; padding: 0; }
  .sv-table th:nth-child(2), .sv-table td:nth-child(2) { display: block; margin-top: .5rem; }
  .sv-table td.num { text-align: left; display: inline-block; margin-top: .6rem; margin-right: 1rem; }
  .sv-table td.num::before { content: attr(data-label); display: block; font-size: .62rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--muted); margin-bottom: .1rem; }
  .sv-table td.basis { margin-top: .5rem; padding-top: .5rem; border-top: 1px dashed var(--line); }

  .sv-panel { width: 100%; }
}

@media (max-width: 400px) {
  .sv-strip-item { padding: .65rem .8rem; flex-direction: column; gap: .1rem; align-items: flex-start; }
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

      <div class="sv-strip">
        <div class="sv-strip-item"><span class="sv-strip-n"><?= $totalServices ?></span><span class="sv-strip-l">Services</span></div>
        <div class="sv-strip-item"><span class="sv-strip-n"><?= count($categories) ?></span><span class="sv-strip-l">Categories</span></div>
        <div class="sv-strip-item"><span class="sv-strip-n"><?= $totalServices - $estCount ?></span><span class="sv-strip-l">Confirmed prices</span></div>
        <div class="sv-strip-item"><span class="sv-strip-n"><?= $estCount ?></span><span class="sv-strip-l">Estimated prices</span></div>
      </div>

      <div class="sv-bar">
        <div class="sv-search">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" id="svSearch" placeholder="Search for a service, e.g. BIR, DTI, cooperative" autocomplete="off">
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

      <div class="sv-legend"><span class="sv-est">EST</span> This price is only an estimate. Always confirm on the quotation before telling the client.</div>

      <div class="sv-tablewrap">
        <table class="sv-table">
          <thead>
            <tr>
              <th style="width:36%;">Service</th>
              <th>Category</th>
              <th class="num">Minimum</th>
              <th class="num">Maximum</th>
              <th>Pricing Basis</th>
            </tr>
          </thead>
          <tbody id="svBody">
            <?php foreach ($services as $s): ?>
              <?php
                $r = $s['rates'][0];
                $isPeso = $r['fmt'] === 'peso';
                $sortMin = $isPeso ? $r['min'] : -1;
                $sortMax = $isPeso ? ($r['max'] ?? $r['min']) : -1;
                $blob = strtolower($s['name'] . ' ' . $s['summary'] . ' ' . implode(' ', $s['includes']) . ' ' . $s['category']);
              ?>
              <tr data-id="<?= $s['id'] ?>" data-cat="<?= htmlspecialchars($s['category']) ?>" data-search="<?= htmlspecialchars($blob) ?>" data-name="<?= htmlspecialchars(strtolower($s['name'])) ?>" data-min="<?= $sortMin ?>" data-max="<?= $sortMax ?>" data-order="<?= $s['id'] ?>">
                <td>
                  <div class="sv-name"><?= htmlspecialchars($s['name']) ?></div>
                  <div class="sv-sum"><?= htmlspecialchars($s['summary']) ?></div>
                </td>
                <td><span class="sv-cat"><?= htmlspecialchars($s['category']) ?></span></td>
                <td class="num" data-label="Minimum">
                  <span class="sv-money"><?= fmtVal($r['min'], $r['fmt']) ?></span>
                  <?php if (!empty($r['est'])): ?><span class="sv-est">EST</span><?php endif; ?>
                </td>
                <td class="num" data-label="Maximum">
                  <?php if ($r['max'] === null): ?>
                    <span class="sv-dash">Varies</span>
                  <?php else: ?>
                    <span class="sv-money"><?= fmtVal($r['max'], $r['fmt']) ?></span>
                  <?php endif; ?>
                </td>
                <td class="basis">
                  <div class="sv-unit"><?= htmlspecialchars($r['unit']) ?></div>
                  <?php if (count($s['rates']) > 1): ?><span class="sv-more">+<?= count($s['rates']) - 1 ?> more price<?= count($s['rates']) - 1 > 1 ? 's' : '' ?></span><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div class="sv-empty" id="svEmpty"><i class="fa-regular fa-folder-open fs-2 d-block mb-2"></i>No services found.</div>
      </div>

    </main>
  </div>
</div>

<div class="sv-overlay" id="svOverlay"></div>
<aside class="sv-panel" id="svPanel" aria-hidden="true">
  <div class="sv-panel-head">
    <div>
      <span class="sv-cat" id="svPanelCat"></span>
      <h2 class="sv-panel-title" id="svPanelTitle"></h2>
    </div>
    <button type="button" class="sv-close" id="svClose" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
  </div>
  <div class="sv-panel-body" id="svPanelBody"></div>
</aside>

<script src="../assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script>
var DATA = <?= json_encode($jsData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
var activeCat = 'all';
var body = document.getElementById('svBody');
var searchInput = document.getElementById('svSearch');
var sortSelect = document.getElementById('svSort');

function esc(s) {
  var d = document.createElement('div');
  d.textContent = s;
  return d.innerHTML;
}

function applyFilters() {
  var q = searchInput.value.trim().toLowerCase();
  var shown = 0;
  var rows = Array.prototype.slice.call(body.querySelectorAll('tr'));

  rows.forEach(function (row) {
    var ok = (activeCat === 'all' || row.getAttribute('data-cat') === activeCat) && (row.getAttribute('data-search') || '').indexOf(q) !== -1;
    row.style.display = ok ? '' : 'none';
    if (ok) shown++;
  });

  var mode = sortSelect.value;
  rows.sort(function (a, b) {
    if (mode === 'name') return a.getAttribute('data-name').localeCompare(b.getAttribute('data-name'));
    if (mode === 'low') return Number(a.getAttribute('data-min')) - Number(b.getAttribute('data-min'));
    if (mode === 'high') return Number(b.getAttribute('data-max')) - Number(a.getAttribute('data-max'));
    return Number(a.getAttribute('data-order')) - Number(b.getAttribute('data-order'));
  });
  rows.forEach(function (row) { body.appendChild(row); });

  document.getElementById('svCount').textContent = shown;
  document.getElementById('svEmpty').style.display = shown === 0 ? 'block' : 'none';
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

function openPanel(id) {
  var d = DATA[id];
  if (!d) return;

  document.querySelectorAll('#svBody tr').forEach(function (r) {
    r.classList.toggle('is-selected', r.getAttribute('data-id') === String(id));
  });

  document.getElementById('svPanelCat').textContent = d.category;
  document.getElementById('svPanelTitle').textContent = d.name;

  var hasEst = d.rates.some(function (r) { return r.est; });
  var html = '<p class="sv-panel-sum">' + esc(d.summary) + '</p>';

  if (hasEst) {
    html += '<div class="sv-estnote"><i class="fa-solid fa-triangle-exclamation mt-1"></i><span>This price is only an estimate. Confirm it first before telling the client.</span></div>';
  }

  html += '<h3 class="sv-sec" style="margin-top:0;">Price</h3>';
  d.rates.forEach(function (r) {
    html += '<div class="sv-rate-card"><div class="k">' + esc(r.label) + '</div><div class="v">' + esc(r.text) + '</div><div class="u">' + esc(r.unit) + '</div></div>';
  });

  html += '<h3 class="sv-sec">What\'s included</h3><ul class="sv-inc">';
  d.includes.forEach(function (i) {
    html += '<li><i class="fa-solid fa-check"></i><span>' + esc(i) + '</span></li>';
  });
  html += '</ul>';

  if (d.note) {
    html += '<div class="sv-warn"><i class="fa-solid fa-circle-exclamation mt-1"></i><span>' + esc(d.note) + '</span></div>';
  }

  document.getElementById('svPanelBody').innerHTML = html;
  document.getElementById('svPanel').classList.add('is-open');
  document.getElementById('svOverlay').classList.add('is-open');
  document.getElementById('svPanel').setAttribute('aria-hidden', 'false');
}

function closePanel() {
  document.getElementById('svPanel').classList.remove('is-open');
  document.getElementById('svOverlay').classList.remove('is-open');
  document.getElementById('svPanel').setAttribute('aria-hidden', 'true');
  document.querySelectorAll('#svBody tr').forEach(function (r) { r.classList.remove('is-selected'); });
}

body.addEventListener('click', function (e) {
  var row = e.target.closest('tr');
  if (row) openPanel(row.getAttribute('data-id'));
});

document.getElementById('svClose').addEventListener('click', closePanel);
document.getElementById('svOverlay').addEventListener('click', closePanel);
document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closePanel(); });
</script>

</body>
</html>