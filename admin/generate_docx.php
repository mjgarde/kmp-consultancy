<?php
/**
 * generate_docx.php — single-file version.
 * Combines the DOCX builder, the contract template, and the endpoint
 * into one deployable file. Just drop this in /admin/.
 *
 * Requires PHP zip extension. No Composer, no PHPWord.
 *
 * URL: generate_docx.php?type=contract&contract_id=123
 *
 * To add a quotation template later, add a build_quotation_docx_xml()
 * function below and a matching branch in the DISPATCH section.
 */

session_name('ADMIN_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Access denied.');
}

if (!extension_loaded('zip')) {
    http_response_code(500);
    exit('The PHP "zip" extension is required to generate DOCX files. Enable it in php.ini (extension=zip) and try again.');
}

$pdo = getConnection();
$type = $_GET['type'] ?? 'contract';


/* ============================================================
 * SECTION 1 — DOCX BUILDER
 * Generic WordprocessingML helpers. Type-agnostic.
 * ============================================================ */

function w_esc(?string $text): string
{
    $text = $text === null ? '' : $text;
    return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function w_val($value): string
{
    $value = $value === null ? '' : trim((string) $value);
    return $value !== '' ? $value : '-';
}

function w_textBlock(string $text): string
{
    $lines = preg_split('/\r\n|\r|\n/', $text);
    $out = '';
    foreach ($lines as $line) {
        if (trim($line) === '') {
            $out .= '<w:p/>';
            continue;
        }
        $out .= '<w:p><w:pPr><w:spacing w:after="60"/><w:jc w:val="both"/></w:pPr>'
            . '<w:r><w:t xml:space="preserve">' . w_esc($line) . '</w:t></w:r></w:p>';
    }
    return $out;
}

function w_heading(string $text): string
{
    return '<w:p><w:pPr><w:pStyle w:val="SectionHeading"/></w:pPr>'
        . '<w:r><w:t xml:space="preserve">' . w_esc($text) . '</w:t></w:r></w:p>';
}

function w_para(string $text = '', bool $bold = false, string $align = 'left', int $size = 20): string
{
    $rpr = '<w:rPr>' . ($bold ? '<w:b/>' : '') . '<w:sz w:val="' . $size . '"/><w:szCs w:val="' . $size . '"/></w:rPr>';
    $jc = $align !== 'left' ? '<w:jc w:val="' . $align . '"/>' : '';
    return '<w:p><w:pPr>' . $jc . '</w:pPr><w:r>' . $rpr
        . '<w:t xml:space="preserve">' . w_esc($text) . '</w:t></w:r></w:p>';
}

function w_spacer(): string
{
    return '<w:p/>';
}

function w_cell(string $text, bool $bold = false, string $align = 'left', string $shade = ''): string
{
    $rpr = $bold ? '<w:rPr><w:b/></w:rPr>' : '';
    $jc = $align !== 'left' ? '<w:jc w:val="' . $align . '"/>' : '';
    $shd = $shade !== '' ? '<w:shd w:val="clear" w:color="auto" w:fill="' . $shade . '"/>' : '';
    return '<w:tc><w:tcPr><w:tcW w:w="0" w:type="auto"/>' . $shd . '</w:tcPr>'
        . '<w:p><w:pPr>' . $jc . '</w:pPr><w:r>' . $rpr
        . '<w:t xml:space="preserve">' . w_esc($text) . '</w:t></w:r></w:p></w:tc>';
}

function w_kvRow(string $k1, string $v1, string $k2 = '', string $v2 = ''): string
{
    return '<w:tr>'
        . w_cell($k1, true, 'left', 'F2F2F2')
        . w_cell($v1)
        . w_cell($k2, true, 'left', 'F2F2F2')
        . w_cell($v2)
        . '</w:tr>';
}

function w_table(string $rowsXml): string
{
    return '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblBorders>'
        . '<w:top w:val="single" w:sz="4" w:space="0" w:color="999999"/>'
        . '<w:left w:val="single" w:sz="4" w:space="0" w:color="999999"/>'
        . '<w:bottom w:val="single" w:sz="4" w:space="0" w:color="999999"/>'
        . '<w:right w:val="single" w:sz="4" w:space="0" w:color="999999"/>'
        . '<w:insideH w:val="single" w:sz="4" w:space="0" w:color="999999"/>'
        . '<w:insideV w:val="single" w:sz="4" w:space="0" w:color="999999"/>'
        . '</w:tblBorders><w:tblLayout w:type="autofit"/></w:tblPr>'
        . $rowsXml . '</w:tbl>';
}

function w_signatureBlock(string $leftName, string $leftRole, string $rightName, string $rightRole): string
{
    $sigCell = function (string $name, string $role) {
        $roleLines = '';
        foreach (preg_split('/\r\n|\r|\n/', $role) as $roleLine) {
            $roleLines .= '<w:p><w:r><w:rPr><w:sz w:val="18"/></w:rPr><w:t xml:space="preserve">' . w_esc($roleLine) . '</w:t></w:r></w:p>';
        }
        return '<w:tc><w:tcPr><w:tcW w:w="0" w:type="auto"/></w:tcPr>'
            . '<w:p><w:pPr><w:spacing w:before="600"/><w:pBdr><w:bottom w:val="single" w:sz="4" w:space="1" w:color="000000"/></w:pBdr></w:pPr><w:r><w:t xml:space="preserve"> </w:t></w:r></w:p>'
            . '<w:p><w:r><w:rPr><w:b/></w:rPr><w:t xml:space="preserve">' . w_esc($name !== '' ? $name : ' ') . '</w:t></w:r></w:p>'
            . $roleLines
            . '<w:p><w:r><w:rPr><w:sz w:val="18"/></w:rPr><w:t xml:space="preserve">Date: ____________________</w:t></w:r></w:p>'
            . '</w:tc>';
    };
    return '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblBorders>'
        . '<w:top w:val="none"/><w:left w:val="none"/><w:bottom w:val="none"/><w:right w:val="none"/>'
        . '<w:insideH w:val="none"/><w:insideV w:val="none"/></w:tblBorders></w:tblPr>'
        . '<w:tr>' . $sigCell($leftName, $leftRole) . $sigCell($rightName, $rightRole) . '</w:tr></w:tbl>';
}

function docx_stream(string $bodyContentXml, string $filename): void
{
    $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:body>' . $bodyContentXml
        . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/>'
        . '<w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134" w:header="0" w:footer="0" w:gutter="0"/>'
        . '</w:sectPr></w:body></w:document>';

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
        . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
        . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
        . '</Types>';

    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
        . '</Relationships>';

    $docRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';

    $coreProps = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
        . 'xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title>' . w_esc($filename) . '</dc:title>'
        . '<dc:creator>KMP ConsultHub</dc:creator>'
        . '</cp:coreProperties>';

    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Calibri"/>'
        . '<w:sz w:val="20"/><w:szCs w:val="20"/></w:rPr></w:rPrDefault></w:docDefaults>'
        . '<w:style w:type="paragraph" w:styleId="SectionHeading"><w:name w:val="Section Heading"/>'
        . '<w:pPr><w:spacing w:before="220" w:after="90"/>'
        . '<w:pBdr><w:bottom w:val="single" w:sz="6" w:space="2" w:color="888888"/></w:pBdr></w:pPr>'
        . '<w:rPr><w:b/><w:sz w:val="21"/><w:caps/></w:rPr></w:style>'
        . '</w:styles>';

    $tmpFile = tempnam(sys_get_temp_dir(), 'docx');
    $zip = new ZipArchive();
    $zip->open($tmpFile, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rels);
    $zip->addFromString('docProps/core.xml', $coreProps);
    $zip->addFromString('word/document.xml', $documentXml);
    $zip->addFromString('word/_rels/document.xml.rels', $docRels);
    $zip->addFromString('word/styles.xml', $styles);
    $zip->close();

    if (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmpFile));
    header('Cache-Control: private, max-age=0, must-revalidate');
    readfile($tmpFile);
    unlink($tmpFile);
    exit;
}


/* ============================================================
 * SECTION 2 — TEMPLATES
 * One function per document type. Same field names used by the
 * sow_contracts.php / cpq_quotations.php view modals.
 * ============================================================ */

function build_contract_docx_xml(array $d): string
{
    $body = '';

    $body .= w_para('KMP INTEGRATED ENTERPRISE, INC.', true, 'left', 26);
    $body .= w_para('Shaping Smarter Solutions.', false, 'left', 16);
    $body .= w_para($d['number'] ?? '', true, 'right', 20);
    $body .= w_para('Status: ' . ($d['status'] ?? ''), false, 'right', 16);
    $body .= w_para('Date Created: ' . w_val($d['created_at'] ?? null), false, 'right', 16);
    $body .= w_para('Date Printed: ' . date('F j, Y'), false, 'right', 16);
    $body .= w_spacer();

    $body .= w_para('STATEMENT OF WORK AND CONTRACT', true, 'center', 26);
    $body .= w_para($d['request'] ?? '', false, 'center', 19);
    $body .= w_spacer();

    $body .= w_heading('1. Contract Overview');
    $body .= w_table(
        w_kvRow('Contract No.', w_val($d['number'] ?? null), 'Quotation No.', w_val($d['quotation_number'] ?? null))
        . w_kvRow('Start Date', w_val($d['start_date'] ?? null), 'End Date', w_val($d['end_date'] ?? null))
        . w_kvRow('Contract Value', '₱' . w_val($d['total'] ?? null), 'Quotation Valid Until', w_val($d['quotation_valid_until'] ?? null))
    );
    $body .= w_spacer();

    $body .= w_heading('2. Client Information');
    $body .= w_table(
        w_kvRow('Company', w_val($d['company'] ?? null), 'Industry', w_val($d['industry'] ?? null))
        . w_kvRow('Contact Person', w_val($d['contact_person'] ?? null), 'Contact Number', w_val($d['contact_number'] ?? null))
        . w_kvRow('Email', w_val($d['email'] ?? null), 'Address', w_val($d['address'] ?? null))
    );
    $body .= w_spacer();

    $body .= w_heading('3. Service Request');
    $body .= w_para('Request Title: ' . w_val($d['request'] ?? null), true);
    $body .= w_para('Required Skill: ' . w_val($d['required_skill'] ?? null));
    $body .= w_spacer();
    $details = trim((string) ($d['request_details'] ?? ''));
    $body .= w_textBlock($details !== '' ? $details : 'No additional details were provided for this request.');
    $body .= w_spacer();

    $body .= w_heading('4. Project Scope');
    $scope = trim((string) ($d['scope_summary'] ?? ''));
    $body .= w_textBlock($scope !== '' ? $scope : 'No project scope provided.');
    $body .= w_spacer();

    $body .= w_heading('5. Scope Items and Fees');
    $itemRows = '<w:tr>'
        . w_cell('#', true, 'center', 'EDEDED')
        . w_cell('Description', true, 'left', 'EDEDED')
        . w_cell('Qty', true, 'right', 'EDEDED')
        . w_cell('Unit Price', true, 'right', 'EDEDED')
        . w_cell('Amount', true, 'right', 'EDEDED')
        . '</w:tr>';
    $items = $d['items'] ?? [];
    if (empty($items)) {
        $itemRows .= '<w:tr>' . w_cell('No items recorded.', false, 'center') . w_cell('') . w_cell('') . w_cell('') . w_cell('') . '</w:tr>';
    } else {
        $i = 1;
        foreach ($items as $item) {
            $itemRows .= '<w:tr>'
                . w_cell((string) $i, false, 'center')
                . w_cell($item['description'] ?? '')
                . w_cell($item['quantity'] ?? '', false, 'right')
                . w_cell('₱' . ($item['unit_price'] ?? ''), false, 'right')
                . w_cell('₱' . ($item['line_total'] ?? ''), false, 'right')
                . '</w:tr>';
            $i++;
        }
    }
    $body .= w_table($itemRows);
    $body .= w_spacer();
    $body .= w_para('Subtotal: ₱' . w_val($d['subtotal'] ?? null), false, 'right');
    $body .= w_para('Tax (' . w_val($d['tax_rate'] ?? null) . '%): ₱' . w_val($d['tax_amount'] ?? null), false, 'right');
    $body .= w_para('TOTAL: ₱' . w_val($d['total'] ?? null), true, 'right', 24);
    $body .= w_spacer();

    $notes = trim((string) ($d['quotation_notes'] ?? ''));
    if ($notes !== '') {
        $body .= w_heading('Quotation Notes');
        $body .= w_textBlock($notes);
        $body .= w_spacer();
    }

    $body .= w_heading('6. Terms and Conditions');
    $terms = trim((string) ($d['terms_conditions'] ?? ''));
    $body .= w_textBlock($terms !== '' ? $terms : 'No terms and conditions provided.');
    $body .= w_spacer();

    $body .= w_heading('7. Authorization');
    $approvedBy = $d['approved_by'] ?? null;
    $approvedText = $approvedBy ? $approvedBy . (!empty($d['approved_at']) ? ' (' . $d['approved_at'] . ')' : '') : '-';
    $body .= w_table(
        w_kvRow('Prepared By', w_val($d['prepared_by'] ?? null), 'Approved By', $approvedText)
    );
    $body .= w_spacer();
    $body .= w_spacer();

    $body .= w_signatureBlock(
        $approvedBy ?: '',
        "Authorized Representative\nKMP Integrated Enterprise, Inc.",
        $d['contact_person'] ?? '',
        "Authorized Representative\n" . ($d['company'] ?? '')
    );
    $body .= w_spacer();

    $body .= w_para('KMP Integrated Enterprise, Inc. · Shaping Smarter Solutions.', false, 'center', 16);
    $body .= w_para('This document was generated by the KMP ConsultHub system. Contract No. ' . w_val($d['number'] ?? null), false, 'center', 16);

    return $body;
}

/*
 * Future — Quotation template.
 * Copy build_contract_docx_xml() as a starting point, then:
 *   - swap section 1 (contract dates → quotation number / status / valid-until)
 *   - drop the signature block (or replace with a single "Prepared By" line)
 *   - remove section 7 (Authorization), renumber 6 → 5, 5 → 4, etc.
 *
 * function build_quotation_docx_xml(array $d): string { ... }
 */


/* ============================================================
 * SECTION 3 — DISPATCH
 * ============================================================ */

if ($type === 'contract') {

    $contractId = (int) ($_GET['contract_id'] ?? 0);
    if (!$contractId) {
        http_response_code(400);
        exit('Missing contract_id.');
    }

    $stmt = $pdo->prepare(
        "SELECT ct.*, c.company_name, c.contact_person, c.email, c.contact_number, c.address, c.industry,
                sr.request_title, sr.request_details, sr.required_skill,
                q.quotation_number, q.subtotal, q.tax_rate, q.tax_amount,
                q.valid_until AS quotation_valid_until, q.notes AS quotation_notes,
                pb.firstname AS prepared_firstname, pb.lastname AS prepared_lastname,
                ab.firstname AS approved_firstname, ab.lastname AS approved_lastname
         FROM contracts ct
         INNER JOIN clients c ON ct.client_id = c.client_id
         INNER JOIN service_requests sr ON ct.request_id = sr.request_id
         INNER JOIN quotations q ON ct.quotation_id = q.quotation_id
         LEFT JOIN users pb ON ct.prepared_by = pb.user_id
         LEFT JOIN users ab ON ct.approved_by = ab.user_id
         WHERE ct.contract_id = ?"
    );
    $stmt->execute([$contractId]);
    $ct = $stmt->fetch();

    if (!$ct) {
        http_response_code(404);
        exit('Contract not found.');
    }

    if ($ct['status'] !== 'Approved') {
        http_response_code(403);
        exit('Only approved contracts can be downloaded.');
    }

    $itemsStmt = $pdo->prepare('SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY sort_order ASC');
    $itemsStmt->execute([$ct['quotation_id']]);
    $itemRows = $itemsStmt->fetchAll();

    $data = [
        'number'                => $ct['contract_number'],
        'status'                => $ct['status'],
        'created_at'            => $ct['created_at'] ? date('M d, Y', strtotime($ct['created_at'])) : null,
        'company'               => $ct['company_name'],
        'contact_person'        => $ct['contact_person'],
        'email'                 => $ct['email'],
        'contact_number'        => $ct['contact_number'],
        'address'               => $ct['address'],
        'industry'              => $ct['industry'],
        'request'               => $ct['request_title'],
        'request_details'       => $ct['request_details'],
        'required_skill'        => $ct['required_skill'],
        'quotation_number'      => $ct['quotation_number'],
        'subtotal'              => number_format((float) $ct['subtotal'], 2),
        'tax_rate'              => rtrim(rtrim(number_format((float) $ct['tax_rate'], 2), '0'), '.'),
        'tax_amount'            => number_format((float) $ct['tax_amount'], 2),
        'total'                 => number_format((float) $ct['total_amount'], 2),
        'quotation_valid_until' => $ct['quotation_valid_until'] ? date('M d, Y', strtotime($ct['quotation_valid_until'])) : null,
        'quotation_notes'       => $ct['quotation_notes'],
        'items'                 => array_map(function ($it) {
            return [
                'description' => $it['description'],
                'quantity'    => rtrim(rtrim(number_format((float) $it['quantity'], 2), '0'), '.'),
                'unit_price'  => number_format((float) $it['unit_price'], 2),
                'line_total'  => number_format((float) $it['line_total'], 2),
            ];
        }, $itemRows),
        'start_date'       => $ct['start_date'] ? date('M d, Y', strtotime($ct['start_date'])) : null,
        'end_date'         => $ct['end_date'] ? date('M d, Y', strtotime($ct['end_date'])) : null,
        'scope_summary'    => $ct['scope_summary'],
        'terms_conditions' => $ct['terms_conditions'],
        'prepared_by'      => trim(($ct['prepared_firstname'] ?? '') . ' ' . ($ct['prepared_lastname'] ?? '')),
        'approved_by'      => $ct['approved_firstname'] ? trim($ct['approved_firstname'] . ' ' . $ct['approved_lastname']) : null,
        'approved_at'      => $ct['approved_at'] ? date('M d, Y', strtotime($ct['approved_at'])) : null,
    ];

    $documentXml = build_contract_docx_xml($data);
    $filename = preg_replace('/[^A-Za-z0-9\-_]/', '_', $ct['contract_number']) . '.docx';

    docx_stream($documentXml, $filename);
    exit;
}

// Future: if ($type === 'quotation') { ... }

http_response_code(400);
exit('Unknown document type.');